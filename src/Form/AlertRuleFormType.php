<?php

namespace App\Form;

use App\Entity\AlertRule;
use App\Enum\Metric;
use App\Enum\Operator;
use App\Enum\Severity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AlertRuleFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('metric', EnumType::class, [
                'label' => 'Métrique',
                'class' => Metric::class,
                'choice_label' => static fn (Metric $m) => $m->label().' ('.$m->unit().')',
            ])
            ->add('operator', EnumType::class, [
                'label' => 'Condition',
                'class' => Operator::class,
                'choice_label' => static fn (Operator $o) => $o->symbol(),
            ])
            ->add('threshold', NumberType::class, ['label' => 'Seuil', 'scale' => 2, 'html5' => true])
            ->add('severity', EnumType::class, [
                'label' => 'Sévérité',
                'class' => Severity::class,
                'choice_label' => static fn (Severity $s) => $s->label(),
            ])
            ->add('cooldownMinutes', IntegerType::class, ['label' => 'Anti-rebond (min)']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AlertRule::class]);
    }
}
