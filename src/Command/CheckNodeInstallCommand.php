<?php

namespace Wexample\SymfonyApi\Command;

use Wexample\SymfonyApi\Command\Traits\AbstractSymfonyApiBundleCommandTrait;
use Wexample\SymfonyHelpers\Command\AbstractCheckNodeInstallCommand;

class CheckNodeInstallCommand extends AbstractCheckNodeInstallCommand
{
    use AbstractSymfonyApiBundleCommandTrait;
}
