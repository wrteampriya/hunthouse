<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GenericMailTemplate extends Mailable
{
    use Queueable, SerializesModels;

    public $data;
    public $adminMail;
    public $companyName;

    public function __construct($data, $adminMail, $companyName)
    {
        $this->data = $data;
        $this->adminMail = $adminMail;
        $this->companyName = $companyName;
    }

    public function build()
    {
        return $this->from($this->adminMail, $this->companyName)
                    ->subject($this->data['title'])
                    ->view('mail-templates.mail-template')
                    ->with($this->data);
    }
}
