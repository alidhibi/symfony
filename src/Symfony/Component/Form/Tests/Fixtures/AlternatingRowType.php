<?php

namespace Symfony\Component\Form\Tests\Fixtures;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class AlternatingRowType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) : void {
            $form = $event->getForm();
            $type = 0 === $form->getName() % 2
                ? \Symfony\Component\Form\Extension\Core\Type\TextType::class
                : \Symfony\Component\Form\Extension\Core\Type\TextareaType::class;
            $form->add('title', $type);
        });
    }
}
