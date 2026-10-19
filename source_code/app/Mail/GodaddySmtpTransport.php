<?php

namespace App\Mail;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;

class GodaddySmtpTransport extends AbstractTransport
{
    private string $host;

    private int $port;

    public function __construct(string $host = 'localhost', int $port = 25)
    {
        parent::__construct();
        $this->host = $host;
        $this->port = $port;
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $socket = fsockopen($this->host, $this->port, $errno, $errstr, 30);

        if (! $socket) {
            throw new \RuntimeException("Could not connect to SMTP: $errstr ($errno)");
        }

        $this->readLine($socket); // 220 greeting

        $this->sendLine($socket, 'EHLO localhost');
        $response = $this->readAll($socket);

        // If server offers STARTTLS, we deliberately skip it
        $this->sendLine($socket, 'MAIL FROM:<'.$email->getFrom()[0]->getAddress().'>');
        $this->readLine($socket);

        foreach ($email->getTo() as $to) {
            $this->sendLine($socket, 'RCPT TO:<'.$to->getAddress().'>');
            $this->readLine($socket);
        }

        $this->sendLine($socket, 'DATA');
        $this->readLine($socket);

        // Send raw email headers + body
        $this->sendLine($socket, $message->toString()."\r\n.");
        $this->readLine($socket);

        $this->sendLine($socket, 'QUIT');
        fclose($socket);
    }

    private function sendLine($socket, string $data): void
    {
        fwrite($socket, $data."\r\n");
    }

    private function readLine($socket): string
    {
        return fgets($socket, 512);
    }

    private function readAll($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            } // End of multi-line response
        }

        return $response;
    }

    public function __toString(): string
    {
        return 'smtp://localhost';
    }
}
