<?php

namespace Wexample\SymfonyApi\Command;

use Wexample\SymfonyApi\Command\Traits\AbstractSymfonyApiBundleCommandTrait;
use Wexample\SymfonyHelpers\Command\AbstractBundleCommand;

class CheckInstallCommand extends AbstractBundleCommand
{
    use AbstractSymfonyApiBundleCommandTrait;
}
