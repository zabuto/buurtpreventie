<?php

namespace Zabuto\Bundle\BuurtpreventieBundle\Form\Type;

use Zabuto\Bundle\BuurtpreventieBundle\Form\Type\LooptoelichtingFormType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class LoopschemaNieuwFormType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('toelichtingen', 'collection', array(
            'type' => new LooptoelichtingFormType(),
            'label' => 'Toelichting voor andere lopers',
            'allow_add' => true,
            'by_reference' => false,
            'allow_delete' => false
        ));
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Zabuto\Bundle\BuurtpreventieBundle\Entity\Loopschema',
        ));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'zabuto_buurtpreventie_form_type_loopschema_nieuw';
    }
}
