<?php

// ruleid: mailer-outside-notifier
$this->mailer->send($message);

// ruleid: mailer-outside-notifier
$mailer->send($message);

// ruleid: mailer-outside-notifier
$this->symfonyMailer->send(new Email());

// ok: mailer-outside-notifier
$this->notifier->send(new Notification($memberId, 'round.reminder', []));

// ok: mailer-outside-notifier
$this->delivery->send($notification);
