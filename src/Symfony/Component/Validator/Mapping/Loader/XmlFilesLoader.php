<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Mapping\Loader;

/**
 * Loads validation metadata from a list of XML files.
 *
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see FilesLoader
 */
class XmlFilesLoader extends FilesLoader
{
    /**
     * {@inheritdoc}
     */
    protected function getFileLoaderInstance($file): \Symfony\Component\Validator\Mapping\Loader\XmlFileLoader
    {
        return new XmlFileLoader($file);
    }
}
