<?php declare(strict_types=1);

namespace Somnambulist\ProjectManager\Commands\Behaviours;

use Somnambulist\Collection\MutableCollection;
use Somnambulist\ProjectManager\Models\Config;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function strtolower;
use function trim;

/**
 * Trait GetServicesFromInput
 *
 * @package Somnambulist\ProjectManager\Commands\Behaviours
 * @subpackage Somnambulist\ProjectManager\Commands\Behaviours\GetServicesFromInput
 *
 * @property-read Config $config
 */
trait GetServicesFromInput
{

    protected function getServicesFrom(InputInterface $input, OutputInterface $output, string $message): MutableCollection
    {
        $services = $input->getArgument('service');

        if (strtolower(trim($services[0])) === 'all') {
            $output->writeln($message);

            return $this->config->projects()->active()->services()->list()->keys();
        }

        return new MutableCollection($services);
    }
}