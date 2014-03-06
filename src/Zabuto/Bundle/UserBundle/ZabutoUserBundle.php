<?php

namespace Zabuto\Bundle\UserBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class ZabutoUserBundle extends Bundle
{

    public function getParent()
    {
        return 'FOSUserBundle';
    }

}
