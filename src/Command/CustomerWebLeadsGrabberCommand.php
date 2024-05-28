<?php

namespace App\Command;

use App\Entity\Vhost;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class CustomerWebLeadsGrabberCommand extends Command
{
    use LockableTrait;

    protected static $defaultName = 'app:customer:webleadgrabber';

    /** @var EntityManagerInterface */
    private $em;

    /** @var Filesystem */
    private $filesystem;

    /** @var array */
    private $env;

    public function __construct(ContainerInterface $container, $name = null)
    {
        $this->em = $container->get('doctrine')->getManager();

        $this->filesystem = new Filesystem();

        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setHelp('Dashboard for all customers command.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->lock()) {
            $output->writeln('The command is already running in another process.');

            return -1;
        }

        $vhosts = $this->em->getRepository(Vhost::class)->findAll();

        try {
            /** @var Vhost $vhost */
            foreach ($vhosts as $vhost) {
                $domain = $vhost->getCustomer()->getDomain();

                if ($domain !== 'ciminocare.seniorcaresw.com') {
                    continue;
                }

                $db = [
                    'host' => $vhost->getDbHost(),
                    'name' => $vhost->getDbName(),
                    'user' => $vhost->getDbUser(),
                    'pass' => $vhost->getDbPassword(),
                ];
                $mailer = [
                    'host' => $vhost->getMailerHost(),
                    'proto' => $vhost->getMailerProto(),
                    'user' => $vhost->getMailerUser(),
                    'pass' => $vhost->getMailerPassword(),
                ];

                $dir_name = [
                    'root' => $vhost->getWwwRoot(),
                    'var' => sprintf("%s/var", $vhost->getWwwRoot()),
                ];

                $output->writeln(sprintf("WebLeadsGrabber for '%s'...", $domain));

                $this->createEnv($db, $mailer, $dir_name, $domain);
                $this->customerDashboard($dir_name['root'], $domain);

                $output->writeln(sprintf("Completed WebLeadsGrabber for '%s'.", $domain));
            }
        } catch (\Throwable $e) {
            dump($e->getMessage());
            dump($e->getTraceAsString());
        }

        $this->release();

        return 0;
    }

    private function customerDashboard($root_dir, $domain)
    {
        $path = [];
        $path['php'] = '/usr/bin/php';
        $path['symfony_console'] = sprintf('%s/bin/console', $root_dir);

        $process = new Process(
            [$path['php'], $path['symfony_console'], 'app:webleadgrabber', $domain],
            null, $this->env, null, 3600
        );

        $process->run();

        dump($process->getOutput());

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    private function createEnv($db, $mailer, $dir_name, $domain)
    {
        $this->env = [
            'APP_ENV' => 'dev',
            'APP_SECRET' => '441e2c01edab863446135746a45396bd',
            'DATABASE_URL' => sprintf('mysql://%s:%s@127.0.0.1:3306/%s', $db['user'], $db['pass'], $db['name']),
            'MAILER_URL' => sprintf('%s://%s:%s@%s', $mailer['proto'], $mailer['user'], $mailer['pass'], $mailer['host']),
            'CORS_ALLOW_ORIGIN' => sprintf('^https?://%s(:[0-9]+)?$', $domain),
            'WKHTMLTOPDF_PATH' => '/usr/local/bin/wkhtmltopdf',
            'WKHTMLTOIMAGE_PATH' => '/usr/local/bin/wkhtmltoimage',
            'AWS_REGION' => 'us-west-1',
            'AWS_VERSION' => 'latest',
            'AWS_KEY' => 'vvv',
            'AWS_SECRET' => 'zxcxzc+VMtlv+',
            'AWS_BUCKET' => $domain
        ];
    }

}
