<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

<<<<<<<< HEAD:symfony/console/Tests/Fixtures/MockableAppliationWithTerminalWidth.php
namespace Symfony\Component\Console\Tests\Fixtures;

use Symfony\Component\Console\Application;

class MockableAppliationWithTerminalWidth extends Application
{
    public function getTerminalWidth(): int
    {
        return 0;
========
namespace Symfony\Component\HttpFoundation\File;

/**
 * A PHP stream of unknown size.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Stream extends File
{
    public function getSize(): int|false
    {
        return false;
>>>>>>>> origin/fundraising/REL1_43:symfony/http-foundation/File/Stream.php
    }
}
