<?php

namespace Zabuto\Bundle\BuurtpreventieBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class LoopschemaAfmeldenFormType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('redenafzegging', 'textarea', array(
                'label' => 'Reden van afmelding',
                'required' => true,
                'attr' => array('rows' => '3'),
            )
        );
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Zabuto\Bundle\BuurtpreventieBundle\Entity\Loopschema'
        ));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'zabuto_buurtpreventie_form_type_loopschema_afmelden';
    }
}
