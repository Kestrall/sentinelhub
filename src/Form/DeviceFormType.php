<?php

namespace App\Form;

use App\Entity\Device;
use App\Entity\Site;
use App\Entity\User;
use App\Enum\DeviceType;
use App\Repository\SiteRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DeviceFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var User $owner */
        $owner = $options['owner'];

        $builder
            ->add('name', TextType::class, ['label' => 'Nom', 'attr' => ['placeholder' => 'Capteur salon']])
            ->add('site', EntityType::class, [
                'label' => 'Site',
                'class' => Site::class,
                'choice_label' => 'name',
                // on ne propose que les sites de l'utilisateur connecté
                'query_builder' => static fn (SiteRepository $r) => $r->createQueryBuilder('s')
                    ->where('s.owner = :owner')->setParameter('owner', $owner)->orderBy('s.name'),
            ])
            ->add('type', EnumType::class, [
                'label' => 'Type',
                'class' => DeviceType::class,
                'choice_label' => static fn (DeviceType $t) => $t->label(),
            ])
            ->add('hardware', ChoiceType::class, [
                'label' => 'Matériel',
                'choices' => array_combine(Device::HARDWARE, Device::HARDWARE),
            ])
            ->add('macAddress', TextType::class, ['label' => 'Adresse MAC', 'attr' => ['placeholder' => 'AA:BB:CC:DD:EE:FF', 'spellcheck' => 'false']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Device::class]);
        $resolver->setRequired('owner');
        $resolver->setAllowedTypes('owner', User::class);
    }
}
