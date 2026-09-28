<?php

namespace App\Form;

use App\Entity\Site;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SiteFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom', 'attr' => ['placeholder' => 'Maison, atelier, garage…']])
            ->add('location', TextType::class, ['label' => 'Localisation', 'required' => false, 'attr' => ['placeholder' => 'Lyon (69)']])
            ->add('timezone', TimezoneType::class, ['label' => 'Fuseau horaire', 'preferred_choices' => ['Europe/Paris']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Site::class]);
    }
}
