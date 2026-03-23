<?php
/**
 * A minimalistic SMTP client for sending emails via SSL/TLS without PHPMailer.
 */
class MinimalSmtpClient {
    private $host;
    private $port;
    private $username;
    private $password;
    private $fromEmail;
    private $fromName;
    private $socket;

    public function __construct($host, $port, $username, $password, $fromEmail, $fromName) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
    }

    private function getServerResponse() {
        $response = '';
        while ($str = fgets($this->socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == " ") break;
        }
        return $response;
    }

    private function sendCommand($command) {
        fputs($this->socket, $command . "\r\n");
        return $this->getServerResponse();
    }

    public function send($to, $subject, $message) {
        $remote = 'ssl://' . $this->host . ':' . $this->port;
        $this->socket = stream_socket_client($remote, $errno, $errstr, 15);
        
        if (!$this->socket) {
            throw new Exception("Connection failed: $errstr ($errno)");
        }

        $this->getServerResponse();
        $this->sendCommand("EHLO " . $_SERVER['SERVER_NAME']);
        $this->sendCommand("AUTH LOGIN");
        $this->sendCommand(base64_encode($this->username));
        $this->sendCommand(base64_encode($this->password));
        $this->sendCommand("MAIL FROM: <" . $this->fromEmail . ">");
        $this->sendCommand("RCPT TO: <" . $to . ">");
        $this->sendCommand("DATA");

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/plain; charset=utf-8\r\n";
        $headers .= "To: <$to>\r\n";
        $headers .= "From: " . $this->fromName . " <" . $this->fromEmail . ">\r\n";
        $headers .= "Subject: " . $subject . "\r\n";
        $headers .= "Date: " . date("r") . "\r\n";
        $headers .= "X-Mailer: ConnectStudio/1.0\r\n";

        $fullMsg = $headers . "\r\n" . $message . "\r\n.";
        $this->sendCommand($fullMsg);
        $this->sendCommand("QUIT");
        fclose($this->socket);
        return true;
    }
}
