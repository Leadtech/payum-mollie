<?php

declare(strict_types=1);

namespace spec\PayHelper\Payum\Mollie\Request\Api;

use Payum\Core\Request\Generic;
use PayHelper\Payum\Mollie\Request\Api\CreateCapture;
use PhpSpec\ObjectBehavior;

final class CreateCaptureSpec extends ObjectBehavior
{
    function let()
    {
        $this->beConstructedWith([]);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(CreateCapture::class);
    }

    function it_should_extends_generic_request()
    {
        $this->shouldHaveType(Generic::class);
    }
}
