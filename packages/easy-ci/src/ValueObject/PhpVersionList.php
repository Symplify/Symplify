<?php

declare(strict_types=1);

namespace Symplify\EasyCI\ValueObject;

final class PhpVersionList
{
    /**
     * @see https://en.wikipedia.org/wiki/PHP#Release_history
     * @var array<string, string>
     */
    public const VERSIONS_BY_RELEASE_DATE = [
        '2009-06-30' => '5.3',
        '2012-03-01' => '5.4',
        '2013-06-20' => '5.5',
        '2014-08-28' => '5.6',
        '2015-12-03' => '7.0',
        '2016-12-01' => '7.1',
        '2017-11-30' => '7.2',
        '2018-12-06' => '7.3',
        '2019-11-28' => '7.4',
        '2020-11-26' => '8.0',
        // ETAs ↓
        '2021-12-01' => '8.1',
        '2022-12-01' => '8.2',
    ];
}
