<?php declare(strict_types=1);

namespace App\Form;

use App\Entity\Round;
use DateTimeImmutable;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RoundTimeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('time', TimeType::class, [
                'mapped' => false,
                'required' => true,
                'widget' => 'single_text',
                'with_seconds' => false,
                'label' => 'walk.round-time',
            ]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, static function (FormEvent $event) {
            /** @var Round|null $round */
            $round = $event->getData();
            $form = $event->getForm();

            if (null !== $round && null !== $round->getDatetime()) {
                $form->get('time')->setData(clone $round->getDatetime());
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            /** @var Round $round */
            $round = $event->getData();
            $form = $event->getForm();

            $string = sprintf('%s %s', $round->getDatetime()?->format('Y-m-d'), $form->get('time')->getData());
            $datetime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $string);
            $round->setDatetime($datetime);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Round::class,
            'date_show' => false,
        ]);
    }
}
