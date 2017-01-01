<?php

	/**
	 * Validate Email Addresses Via SMTP
	 * This queries the SMTP server to see if the email address is accepted.
	 * @copyright http://creativecommons.org/licenses/by/2.0/ - Please keep this comment intact
	 * @author    gabe@fijiwebdesign.com
	 * @contributers adnan@barakatdesigns.net
	 * @version   0.1a
	 */
	class SMTP_validateEmail {

		public $debug = FALSE;
		/**
		 * PHP Socket resource to remote MTA
		 * @var resource $sock
		 */
		private $sock;
		/**
		 * Current User being validated
		 */
		private $user;
		/**
		 * Current domain where user is being validated
		 */
		private $domain;
		/**
		 * List of domains to validate users on
		 */
		private $domains;
		/**
		 * SMTP Port
		 */
		private $port = 25;
		/**
		 * Maximum Connection Time to an MTA
		 */
		private $max_conn_time = 30;
		/**
		 * Maximum time to read from socket
		 */
		private $max_read_time = 5;
		/**
		 * username of sender
		 */
		private $from_user = 'user';
		/**
		 * Host Name of sender
		 */
		private $from_domain = 'localhost';
		/**
		 * Nameservers to use when make DNS query for MX entries
		 * @var Array $nameservers
		 */
		private $nameservers = array(
			'192.168.0.1'
		);

		/**
		 * Initializes the Class
		 * @return SMTP_validateEmail Instance
		 *
		 * @param $email  Array[optional] List of Emails to Validate
		 * @param $sender String[optional] Email of validator
		 */
		public function __construct( $emails = FALSE, $sender = FALSE ) {
			if ( $emails ) {
				$this->setEmails( $emails );
			}
			if ( $sender ) {
				$this->setSenderEmail( $sender );
			}
		}

		/**
		 * Set the Emails to validate
		 *
		 * @param $emails Array List of Emails
		 */
		private function setEmails( $emails ) {
			foreach ( $emails as $email ) {
				list( $user, $domain ) = $this->_parseEmail( $email );
				if ( ! isset( $this->domains[ $domain ] ) ) {
					$this->domains[ $domain ] = array();
				}
				$this->domains[ $domain ][] = $user;
			}
		}

		private function _parseEmail( $email ) {
			$parts  = explode( '@', $email );
			$domain = array_pop( $parts );
			$user   = implode( '@', $parts );

			return array( $user, $domain );
		}

		/**
		 * Set the Email of the sender/validator
		 *
		 * @param $email String
		 */
		private function setSenderEmail( $email ) {
			$parts             = $this->_parseEmail( $email );
			$this->from_user   = $parts[ 0 ];
			$this->from_domain = $parts[ 1 ];
		}

		/**
		 * Validate Email Addresses
		 *
		 * @param String $emails Emails to validate (recipient emails)
		 * @param String $sender Sender's Email
		 *
		 * @return Array Associative List of Emails and their validation results
		 */
		public function validate( $emails = FALSE, $sender = FALSE ) {

			$results = array();

			if ( $emails ) {
				$this->setEmails( $emails );
			}

			if ( $sender ) {
				$this->setSenderEmail( $sender );
			}

			// query the MTAs on each Domain
			foreach ( $this->domains as $domain => $users ) {

				$mxs = array();


				if ( in_array( $domain, get_disposable_emails() ) ) {

					$e             = trim( $_GET[ 'email' ] );
					$results[ $e ] = FALSE;
					continue;
				}


				// retrieve SMTP Server via MX query on domain
				list( $hosts, $mxweights ) = $this->queryMX( $domain );

				// retrieve MX priorities
				for ( $n = 0; $n < count( $hosts ); $n ++ ) {
					$mxs[ $hosts[ $n ] ] = $mxweights[ $n ];
				}
				asort( $mxs );

				// last fallback is the original domain
				array_push( $mxs, $this->domain );

				$this->debug( print_r( $mxs, 1 ) );

				$timeout = $this->max_conn_time / ( count( $hosts ) > 0 ? count( $hosts ) : 1 );

				// try each host
				while ( list( $host ) = each( $mxs ) ) {
					// connect to SMTP server
					$this->debug( "try $host:$this->port\n" );
					if ( $this->sock = fsockopen( $host, $this->port, $errno, $errstr, (float) $timeout ) ) {
						stream_set_timeout( $this->sock, $this->max_read_time );
						break;
					}
				}

				// did we get a TCP socket
				if ( $this->sock ) {
					$reply = fread( $this->sock, 2082 );
					$this->debug( "<<<\n$reply" );

					preg_match( '/^([0-9]{3})/ims', $reply, $matches );
					$code = isset( $matches[ 1 ] ) ? $matches[ 1 ] : '';

					if ( $code != '220' ) {
						// MTA gave an error...
						foreach ( $users as $user ) {
							$results[ $user . '@' . $domain ] = FALSE;
						}
						continue;
					}

					// say helo
					$this->send( "HELO " . $this->from_domain );
					// tell of sender
					$this->send( "MAIL FROM: <" . $this->from_user . '@' . $this->from_domain . ">" );

					// ask for each recepient on this domain
					foreach ( $users as $user ) {

						// ask of recepient
						$reply = $this->send( "RCPT TO: <" . $user . '@' . $domain . ">" );

						// get code and msg from response
						preg_match( '/^([0-9]{3}) /ims', $reply, $matches );
						$code = isset( $matches[ 1 ] ) ? $matches[ 1 ] : '';

						if ( $code == '250' ) {
							// you received 250 so the email address was accepted
							$results[ $user . '@' . $domain ] = TRUE;
						} elseif ( $code == '451' || $code == '452' ) {
							// you received 451 so the email address was greylisted (or some temporary error occured on the MTA) - so assume is ok
							$results[ $user . '@' . $domain ] = TRUE;
						} else {
							$results[ $user . '@' . $domain ] = FALSE;
						}

					}

					// quit
					$this->send( "quit" );
					// close socket
					fclose( $this->sock );

				}
			}

			return $results;
		}

		/**
		 * Query DNS server for MX entries
		 * @return
		 */
		private function queryMX( $domain ) {
			$hosts     = array();
			$mxweights = array();
			if ( function_exists( 'getmxrr' ) ) {
				getmxrr( $domain, $hosts, $mxweights );
			} else {
				// windows, we need Net_DNS
				require_once 'Net/DNS.php';

				$resolver        = new Net_DNS_Resolver();
				$resolver->debug = $this->debug;
				// nameservers to query
				$resolver->nameservers = $this->nameservers;
				$resp                  = $resolver->query( $domain, 'MX' );
				if ( $resp ) {
					foreach ( $resp->answer as $answer ) {
						$hosts[]     = $answer->exchange;
						$mxweights[] = $answer->preference;
					}
				}

			}

			return array( $hosts, $mxweights );
		}

		private function debug( $str ) {
			if ( $this->debug ) {
				echo htmlentities( $str );
			}
		}

		private function send( $msg ) {
			fwrite( $this->sock, $msg . "\r\n" );

			$reply = fread( $this->sock, 2082 );

			$this->debug( ">>>\n$msg\n" );
			$this->debug( "<<<\n$reply" );

			return $reply;
		}

		/**
		 * Simple function to replicate PHP 5 behaviour. http://php.net/microtime
		 */
		private function microtime_float() {
			list( $usec, $sec ) = explode( " ", microtime() );

			return ( (float) $usec + (float) $sec );
		}
	}