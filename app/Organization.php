<?php

namespace App;

enum Organization: int
{
    case OPCO_MIN_DURATION = 4 * 60;
    case OPCO_MAX_DURATION = 10 * 60;
    case OPCO_UNIT_PRICE = 20;

    case FIFPL_MIN_DURATION = 3 * 60;
    case FIFPL_MAX_DURATION = 6 * 60;
    case FIFPL_UNIT_PRICE = 30;
}
