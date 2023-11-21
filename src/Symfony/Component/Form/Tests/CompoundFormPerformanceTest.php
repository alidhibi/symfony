<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use Symfony\Component\Form\Test\FormPerformanceTestCase;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CompoundFormPerformanceTest extends FormPerformanceTestCase
{
    /**
     * Create a compound form multiple times, as happens in a collection form.
     *
     * @group benchmark
     */
    public function testArrayBasedForm(): void
    {
        $this->setMaxRunningTime(1);

        for ($i = 0; $i < 40; ++$i) {
            $form = $this->factory->createBuilder(\Symfony\Component\Form\Extension\Core\Type\FormType::class)
                ->add('firstName', \Symfony\Component\Form\Extension\Core\Type\TextType::class)
                ->add('lastName', \Symfony\Component\Form\Extension\Core\Type\TextType::class)
                ->add('color', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, [
                    'choices' => ['red' => 'Red', 'blue' => 'Blue'],
                    'required' => false,
                ])
                ->add('age', \Symfony\Component\Form\Extension\Core\Type\NumberType::class)
                ->add('birthDate', \Symfony\Component\Form\Extension\Core\Type\BirthdayType::class)
                ->add('city', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, [
                    // simulate 300 different cities
                    'choices' => range(1, 300),
                ])
                ->getForm();

            // load the form into a view
            $form->createView();
        }
    }
}
