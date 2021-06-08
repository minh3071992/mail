<?php

namespace App;

use Aws\Ses\SesClient;
use Closure;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Log\LogManager;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Mail\Transport\LogTransport;
use Illuminate\Mail\Transport\MailgunTransport;
use Illuminate\Mail\Transport\SesTransport;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Postmark\ThrowExceptionOnFailurePlugin;
use Postmark\Transport as PostmarkTransport;
use Psr\Log\LoggerInterface;
use Swift_DependencyContainer;
use Swift_Mailer;
use Swift_SendmailTransport as SendmailTransport;
use Swift_SmtpTransport as SmtpTransport;
use Illuminate\Mail\Mailer;

class MailMagazineManager
{
    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function mailer($config)
    {
        $driver = $config['transport'];
        if (is_null($driver)) {
            throw new InvalidArgumentException("Mail magazine transport not defined");
        }
        $mailer = new Mailer($this->app['view'], $this->createSwiftMailer($config), $this->app['events']);

        return $mailer;
    }

    public function createSwiftMailer(array $config)
    {
        // if ($config['domain'] ?? false) {
        //     Swift_DependencyContainer::getInstance()
        //         ->register('mime.idgenerator.idright')
        //         ->asValue($config['domain']);
        // }

        return new Swift_Mailer($this->createTransport($config));
    }

    public function createTransport(array $config)
    {
        $transport = $config['transport'];
        if (trim($transport) === '' || !method_exists($this, $method = 'create' . ucfirst($transport) . 'Transport')) {
            throw new InvalidArgumentException("Unsupported mail transport [{$transport}].");
        }

        return $this->{$method}($config);
    }

    /**
     * Create an instance of the SMTP Swift Transport driver.
     *
     * @param  array  $config
     * @return \Swift_SmtpTransport
     */
    protected function createSmtpTransport(array $config)
    {
        // The Swift SMTP transport instance will allow us to use any SMTP backend
        // for delivering mail such as Sendgrid, Amazon SES, or a custom server
        // a developer has available. We will just pass this configured host.
        $transport = new SmtpTransport(
            $config['host'],
            $config['port']
        );

        if (!empty($config['encryption'])) {
            $transport->setEncryption($config['encryption']);
        }

        // Once we have the transport we will check for the presence of a username
        // and password. If we have it we will set the credentials on the Swift
        // transporter instance so that we'll properly authenticate delivery.
        if (isset($config['username'])) {
            $transport->setUsername($config['username']);

            $transport->setPassword($config['password']);
        }

        return $this->configureSmtpTransport($transport, $config);
    }

    /**
     * Configure the additional SMTP driver options.
     *
     * @param  \Swift_SmtpTransport  $transport
     * @param  array  $config
     * @return \Swift_SmtpTransport
     */
    protected function configureSmtpTransport($transport, array $config)
    {
        if (isset($config['stream'])) {
            $transport->setStreamOptions($config['stream']);
        }

        if (isset($config['source_ip'])) {
            $transport->setSourceIp($config['source_ip']);
        }

        if (isset($config['local_domain'])) {
            $transport->setLocalDomain($config['local_domain']);
        }

        if (isset($config['timeout'])) {
            $transport->setTimeout($config['timeout']);
        }

        if (isset($config['auth_mode'])) {
            $transport->setAuthMode($config['auth_mode']);
        }

        return $transport;
    }

    /**
     * Create an instance of the Amazon SES Swift Transport driver.
     *
     * @param  array  $config
     * @return \Illuminate\Mail\Transport\SesTransport
     */
    protected function createSesTransport(array $config)
    {
        // if (! isset($config['secret'])) {
        //     $config = array_merge($this->app['config']->get('services.ses', []), [
        //         'version' => 'latest', 'service' => 'email',
        //     ]);
        // }

        $config = Arr::except($config, ['transport']);

        return new SesTransport(
            new SesClient($this->addSesCredentials($config)),
            $config['options'] ?? []
        );
    }

    /**
     * Add the SES credentials to the configuration array.
     *
     * @param  array  $config
     * @return array
     */
    protected function addSesCredentials(array $config)
    {
        if (! empty($config['key']) && ! empty($config['secret'])) {
            $config['credentials'] = Arr::only($config, ['key', 'secret', 'token']);
        }

        return $config;
    }
}
