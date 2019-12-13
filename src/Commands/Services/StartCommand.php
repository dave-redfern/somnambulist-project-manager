<?php declare(strict_types=1);

namespace Somnambulist\ProjectManager\Commands\Services;

use Somnambulist\ProjectManager\Commands\AbstractCommand;
use Somnambulist\ProjectManager\Commands\Behaviours\DockerAwareCommand;
use Somnambulist\ProjectManager\Commands\Behaviours\GetCurrentActiveProject;
use Somnambulist\ProjectManager\Commands\Behaviours\GetServicesFromInput;
use Somnambulist\ProjectManager\Contracts\DockerAwareInterface;
use Somnambulist\ProjectManager\Models\Service;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function strtolower;
use function trim;

/**
 * Class StartCommand
 *
 * @package Somnambulist\ProjectManager\Commands\Services
 * @subpackage Somnambulist\ProjectManager\Commands\Services\StartCommand
 */
class StartCommand extends AbstractCommand implements DockerAwareInterface
{

    use GetServicesFromInput;
    use GetCurrentActiveProject;
    use DockerAwareCommand;

    protected function configure()
    {
        $this
            ->setName('services:start')
            ->setDescription('Starts the specified service(s)')
            ->addArgument('service', InputArgument::REQUIRED|InputArgument::IS_ARRAY, 'The services to start, or "all"; see <info>services:list</info> for available services')
            ->addOption('rebuild', 'b', InputOption::VALUE_NONE, 'Re-build the containers before starting')
            ->addOption('refresh', 'r', InputOption::VALUE_NONE, 'Refresh the containers before starting; pulls all new images')
            ->addOption('with-deps', 'd', InputOption::VALUE_NONE, 'Start all dependencies without prompting for confirmation')
            ->addOption('without-deps', 'w', InputOption::VALUE_NONE, 'Ignore all dependencies')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setIsDebugging($input);
        $this->setupConsoleHelper($input, $output);

        $services = $this->getServicesFrom($input, 'Starting all services, this might take a while...');

        foreach ($services as $name) {
            $this->startService($name);
        }

        return 0;
    }

    private function startService(string $service): void
    {
        $project = $this->getActiveProject();

        /** @var Service $service */
        if (null === $service = $project->services()->get($service)) {
            $this->tools()->error('service <comment>%s</comment> not found!', $service);
            return;
        }

        if ($service->hasDependencies()) {
            $deps = null;

            if ($this->tools()->input()->getOption('with-deps')) {
                $deps = 'y';
            }
            if (!$this->tools()->input()->getOption('without-deps')) {
                $deps = 'n';
            }
            if (!$deps) {
                $deps = $this->tools()->ask('Service %s has dependencies, do you want these to be started? (y/n) ', false, $service->name());
            }

            if (strtolower(trim($deps)) === 'y') {
                $service->dependencies()->each(function ($name) {
                    $this->startService($name);
                });
            }
        }

        $command = $this->tools()->input()->getOption('refresh') ? 'refresh' : ($this->tools()->input()->getOption('rebuild') ? 'build' : 'start');

        switch ($command):
            case 'refresh':
                $this->isDebug() ?: $this->tools()->info('attempting to <info>refresh</info> service <comment>%s</comment> ', $service->name());
                $this->docker->refresh($service);
                $this->docker->start($service);
            break;

            case 'build':
                $this->isDebug() ?: $this->tools()->info('attempting to <info>build</info> service <comment>%s</comment> ', $service->name());
                $this->docker->build($service);
                $this->docker->start($service);
            break;

            case 'start' && !$service->isRunning():
                $this->docker->start($service);
        endswitch;

        $service->isRunning()
            ?
            $this->tools()->success('service started <info>successfully</info>')
            :
            $this->tools()->error('service did not start, re-run with <info>-vvv</info> or use <info>docker-compose</info>')
        ;
        $this->tools()->newline();
    }
}
