<?php 

namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
// ce service va nous permettre d'envoyer un email aux utilisateurs qui se sont inscrit 
class SendMailService{
    private $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    // 
    /**
     * méthode send() va nous permettre d'envoyer un mail au moment de l'inscription. 
     *
     * @param string $from nous sert à renseigner le mail depuis lequel on envoie.
     * @param string $to nous sert à savoir le mail de destination.
     * @param string $subject est l'objet du mail.
     * @param string $template va nous permettre de faire un mail personnaliser avce du twig.
     * @param array $context  va contenir les différentes fonctionalités que l'on va utiliser dans le projet.
     * @return void Notre méthode retourne rien :).
     */
    public function send(
        string $from,
        string $to,
        string $subject,
        string $template,
        array $context
        ):void
    {
        $email = (new TemplatedEmail())
            ->from($from)
            ->to($to)
            ->subject($subject)
            ->htmlTemplate("email/$template.html.twig")
            ->context($context);

        // on envoie le mail 
        $this->mailer->send($email);
    }
}