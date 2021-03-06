<?php declare(strict_types=1);

namespace Somnambulist\ProjectManager\Commands\Behaviours;

use Somnambulist\ProjectManager\Contracts\InstallableResource;
use Somnambulist\ProjectManager\Contracts\InstallableResourcesCollection;
use Somnambulist\ProjectManager\Exceptions\ResourceAlreadyInstalled;
use Somnambulist\ProjectManager\Exceptions\ResourceIsNotConfigured;
use Somnambulist\ProjectManager\Services\Console\ConsoleHelper;
use function file_exists;
use function mkdir;

/**
 * Trait InstallableResourceSetupHelpers
 *
 * @package Somnambulist\ProjectManager\Commands\Behaviours
 * @subpackage Somnambulist\ProjectManager\Commands\Behaviours\InstallableResourceSetupHelpers
 *
 * @method ConsoleHelper tools()
 */
trait InstallableResourceSetupHelpers
{

    protected function assertResourceIsConfigured(InstallableResourcesCollection $resources, string $name): InstallableResource
    {
        if (null === $resource = $resources->get($name)) {
            throw ResourceIsNotConfigured::raise($name);
        }

        return $resource;
    }

    protected function assertNotInstalled(InstallableResource $resource): int
    {
        if ($resource->isInstalled()) {
            throw ResourceAlreadyInstalled::raise($resource->name());
        }

        return 0;
    }

    protected function createProjectDirIfNotExists(InstallableResource $resource): int
    {
        if (!file_exists($resource->installPath())) {
            $this->tools()->warning('creating project directory at: <info>%s</info>', $resource->installPath());

            if (!mkdir($resource->installPath(), 0775, true)) {
                $this->tools()->error('failed to create folder <info>%s</info>', $resource->installPath());
                $this->tools()->newline();

                return 1;
            }
        }

        return 0;
    }

    protected function createCloneOfRepository(InstallableResource $resource): int
    {
        $this->tools()->warning('cloning project from <info>%s</info> to <info>%s</info>', $resource->repository(), $resource->installPath());

        $cwd = $_SERVER['PROJECT_DIR'];

        if (!$this->tools()->git()->clone($cwd, $resource->repository(), $resource->installPath())) {
            $this->tools()->error('project setup failed to clone repository');
            $this->tools()->question('do you have access to the project and is Git SSH access configured?');

            $this->tools()->newline();

            return 1;
        }

        $this->tools()->success('successfully cloned repository to <info>%s</info>', $resource->installPath());

        return 0;
    }
}
