<?php

namespace Tests\Concerns;

/**
 * Marker: in a test class that uses this, every existing business has "answered" how it delivers (the two ordinary
 * options ticked, rolled back with the test). A business that has not cannot show products to customers
 * («اجعل الحساب لا يمكن أن يعرض منتجات دون اختيار طرق الاستلام والتسليم»), which has nothing to do with what these
 * tests are about. Tests of that rule itself do not use it.
 */
trait AnswersDelivery
{
}
