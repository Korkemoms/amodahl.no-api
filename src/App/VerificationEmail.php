<?php

class VerificationEmail {

  public static function sendVerificationEmail($emailAddress, $token) {
    $mail = new PHPMailer;

    //$mail->SMTPDebug = 3;

    $mail->isSMTP();
    $mail->Host = getenv("SMTP_HOST");
    $mail->Port = getenv("SMTP_PORT");
    $mail->SMTPAuth = true;
    $mail->Username = getenv("EMAIL_USERNAME");
    $mail->Password = getenv("EMAIL_PASSWORD");
    $mail->SMTPSecure = getenv("EMAIL_ENCRYPTION");

    $mail->setFrom('email_verification@amodahl.no', 'Email verification');
    $mail->addAddress($emailAddress);

    $link = getenv("PAGE_URL") . '/chess/activate-account/' . $emailAddress . '/' . $token;

    $mail->Subject = 'Email verification';
    $mail->Body    = 'Follow the link to activate your account at amodahl.no/chess: <br><a href="' .
    $link . '">Activate account</a>';
    $mail->AltBody = 'Visit the url to activate your account at amodahl.no/chess: ' . getenv("PAGE_URL") .
      '/email-verified/' . $emailAddress . '/' . $token;

    if(!$mail->send()) {
        return 'Message could not be sent. Mailer Error: ' . $mail->ErrorInfo;
    } else {
        return true;
    }

  }

}
