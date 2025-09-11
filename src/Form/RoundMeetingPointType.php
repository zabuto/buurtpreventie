<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\MeetingPoint;
use App\Entity\Round;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RoundMeetingPointType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('meetingPoint', EntityType::class, [
                'class' => MeetingPoint::class,
                'label' => 'walk.meeting-point',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Round::class,
            'date_show' => false,
        ]);
    }
}
