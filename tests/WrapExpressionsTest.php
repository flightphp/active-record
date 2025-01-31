<?php

namespace flight\tests;

use flight\Expressions;
use flight\WrapExpressions;

class WrapExpressionsTest extends \PHPUnit\Framework\TestCase
{
    public function testSingleCondition()
    {
        $wrap = new WrapExpressions([
            'delimiter' => ' ',
            'target' => [
                'source' => 'name',
                'operator' => '=',
                'target' => 'John'
            ]
        ]);

        $this->assertEquals('(name = John)', $wrap->__toString());
    }

    public function testMultipleConditions()
    {
        $wrap = new WrapExpressions([
            'delimiter' => ' AND ',
            'target' => [
                new Expressions([
                    'source' => 'name',
                    'operator' => '=',
                    'target' => 'John'
                ]),
                new Expressions([
                    'source' => 'email',
                    'operator' => '=',
                    'target' => 'some@thing.com'
                ])
            ],
        ]);

        $this->assertEquals('(name = John AND email = some@thing.com)', $wrap->__toString());
    }

    public function testMultipleConditionsInMultipleWraps()
    {
        $wrap = new WrapExpressions([
            'delimiter' => ' OR ',
            'target' => [
                new WrapExpressions([
                    'delimiter' => ' AND ',
                    'target' => [
                        new Expressions([
                            'source' => 'name',
                            'operator' => '=',
                            'target' => 'John'
                        ]),
                        new Expressions([
                            'source' => 'email',
                            'operator' => '=',
                            'target' => 'some@thing.com'
                        ])
                    ]
                ]),
                new WrapExpressions([
                    'delimiter' => ' BECAUSE ',
                    'target' => [
                        new Expressions([
                            'source' => 'name',
                            'operator' => '=',
                            'target' => 'Jane'
                        ]),
                        new Expressions([
                            'source' => 'email',
                            'operator' => '=',
                            'target' => 'some@thing.com'
                        ])
                    ]
                ])
            ]
        ]);

        $this->assertEquals('((name = John AND email = some@thing.com) OR (name = Jane BECAUSE email = some@thing.com))', $wrap->__toString());
    }
}
