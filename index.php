<?php

// IT DOESNOT WORKS ON VAGRANT

require_once "smtpvalidateclass.php";
require_once "disposable.php";

$result = '';
$e = NULL;

if ( isset( $_GET[ 'email' ] ) ):

	$email = trim( $_GET[ 'email' ] );

	// the email to validate
	$emails = array( $email );
	// an optional sender
	$sender = 'uicookiez@gmail.com';
	// instantiate the class
	$SMTP_Valid = new SMTP_validateEmail();
	// do the validation
	$result = $SMTP_Valid->validate( $emails, $sender );

	$e = $result[ $email ];

endif;

?><!doctype html>
<html class="no-js" lang="">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<title></title>
	<meta name="description" content="">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<link rel="stylesheet" href="css/bootstrap.min.css">
	<style>
		body {
			padding-top    : 50px;
			padding-bottom : 20px;
			}
	</style>
	<link rel="stylesheet" href="css/main.css">
</head>
<body>

<div class="container">
	<div class="row">
		<div class="col-md-12">
			<form class="form-inline">
				<div class="form-group">
					<label for="email">Email</label>
					<input type="email" style="width: 600px" required="required" name="email" class="form-control input-lg" id="email" placeholder="">
					<input type="submit" class="btn btn-primary btn-lg" value="check">
				</div>
			</form>

			<?php
				if ( ! is_null( $e ) ) {
					echo ( $e ? '<p class="alert alert-success">' . $email . ' is valid</p>' : '<p class="alert alert-danger">' . $email . ' is invalid</p>' ) . "\n";
				}
			?>
		</div>
	</div>
</div>
</body>
</html>