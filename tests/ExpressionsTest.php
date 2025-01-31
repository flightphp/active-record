<?php

namespace flight\tests;

use flight\Expressions;

class ExpressionsTest extends \PHPUnit\Framework\TestCase
{
    public function testSimpleCondition()
    {
        $wrap = new Expressions([
            'source' => 'name',
            'operator' => '=',
            'target' => 'John'
        ]);

        $this->assertEquals('name = John', $wrap->__toString());
    }

    public function testExpressionsTarget()
    {
        $wrap = new Expressions([
            'operator' => 'WHERE',
            'target' => new Expressions([
                'source' => 'name',
                'operator' => '=',
                'target' => 'John'
            ])
        ]);

        $this->assertEquals(' WHERE name = John', $wrap->__toString());
    }

    public function testMultipleExpressionsTarget()
    {
        $wrap = new Expressions([
            'operator' => 'WHERE',
            'target' => new Expressions([
                    'source' => new Expressions([
                        'source' => 'name',
                        'operator' => '=',
                        'target' => 'John'
                    ]),
                    'operator' => 'AND',
                    'target' => new Expressions([
                        'source' => 'email',
                        'operator' => '=',
                        'target' => 'some@thing.com'
                    ])
                ]),

        ]);

        $this->assertEquals(' WHERE name = John AND email = some@thing.com', $wrap->__toString());
    }

    public function testMultipleExpressionsTargetInMultipleWraps()
    {
        $wrap = new Expressions([
            'operator' => 'WHERE',
            'target' => new Expressions([
                    'source' => new Expressions([
                        'source' => 'name',
                        'operator' => '=',
                        'target' => 'John'
                    ]),
                    'operator' => 'AND',
                    'target' => new Expressions([
                        'source' => new Expressions([
                                'source' => 'email',
                                'operator' => '=',
                                'target' => 'some@thing.com'
                            ]),
                        'operator' => 'AND',
                        'target' => new Expressions([
                            'source' => 'company',
                            'operator' => '=',
                            'target' => 'Acme'
                        ])
                    ])
                ]),
            ]);

        $this->assertEquals(' WHERE name = John AND email = some@thing.com AND company = Acme', $wrap->__toString());
    }
}
