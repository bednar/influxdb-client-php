<?php

namespace InfluxDB2Test;

use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InfluxDB2\WriteType;
use Webmozart\Assert\Assert;

require_once('BasicTest.php');
/**
 * Class WriteApiBatchingTest
 * @package InfluxDB2Test
 */
class WriteApiBatchingTest extends BasicTest
{
    protected function getWriteOptions(): ?array
    {
        return array('writeType' => 2, 'batchSize' => 2, 'flushInterval' => 5000);
    }

    public function testBatchSize()
    {
        $this->mockHandler->append(new Response(204),
            new Response(204));

        $this->writeApi->write('h2o_feet,location=coyote_creek level\\ water_level=1.0 1');
        $this->writeApi->write('h2o_feet,location=coyote_creek level\\ water_level=2.0 2');
        $this->writeApi->write('h2o_feet,location=coyote_creek level\\ water_level=3.0 3');
        $this->writeApi->write('h2o_feet,location=coyote_creek level\\ water_level=4.0 4');

        $this->assertEquals(2, count($this->container));

        $result1 = "h2o_feet,location=coyote_creek level\\ water_level=1.0 1\n"
            . "h2o_feet,location=coyote_creek level\\ water_level=2.0 2";
        $result2 = "h2o_feet,location=coyote_creek level\\ water_level=3.0 3\n"
            . "h2o_feet,location=coyote_creek level\\ water_level=4.0 4";

        $this->assertEquals($result1, $this->container[0]['request']->getBody());
        $this->assertEquals($result2, $this->container[1]['request']->getBody());
    }

    public function testBatchSizeGroupBy()
    {
        $this->mockHandler->append(new Response(204),
            new Response(204),
            new Response(204),
            new Response(204),
            new Response(204));

        $bucket = 'my-bucket';
        $bucket2 = 'my-bucket2';

        $this->writeApi->write('h2o_feet,location=coyote_creek level\\ water_level=1.0 1', 'ns',
            $bucket, 'my-org');
        $this->writeApi->write('h2o_feet,location=coyote_creek level\\ water_level=2.0 2', 's',
            $bucket, 'my-org');
        $this->writeApi->write('h2o_feet,location=coyote_creek level\\ water_level=3.0 3', 'ns',
            $bucket, 'my-org-a');
        $this->writeApi->write('h2o_feet,location=coyote_creek level\\ water_level=4.0 4', 'ns',
            $bucket, 'my-org-a');
        $this->writeApi->write('h2o_feet,location=coyote_creek level\\ water_level=5.0 5', 'ns',
            $bucket2, 'my-org-a');
        $this->writeApi->write('h2o_feet,location=coyote_creek level\\ water_level=6.0 6', 'ns',
            $bucket, 'my-org-a');

        $this->assertEquals(5, count($this->container));

        $request = $this->container[0]['request'];

        $this->assertEquals('http://localhost:9999/api/v2/write?org=my-org&bucket=my-bucket&precision=ns',
            strval($request->getUri()));
        $this->assertEquals('h2o_feet,location=coyote_creek level\\ water_level=1.0 1', $request->getBody());

        $request = $this->container[1]['request'];

        $this->assertEquals('http://localhost:9999/api/v2/write?org=my-org&bucket=my-bucket&precision=s',
            strval($request->getUri()));
        $this->assertEquals('h2o_feet,location=coyote_creek level\\ water_level=2.0 2', $request->getBody());

        $request = $this->container[2]['request'];

        $this->assertEquals('http://localhost:9999/api/v2/write?org=my-org-a&bucket=my-bucket&precision=ns',
            strval($request->getUri()));
        $this->assertEquals("h2o_feet,location=coyote_creek level\\ water_level=3.0 3\n"
            . 'h2o_feet,location=coyote_creek level\\ water_level=4.0 4', $request->getBody());

        $request = $this->container[3]['request'];

        $this->assertEquals('http://localhost:9999/api/v2/write?org=my-org-a&bucket=my-bucket2&precision=ns',
            strval($request->getUri()));
        $this->assertEquals('h2o_feet,location=coyote_creek level\\ water_level=5.0 5', $request->getBody());

        $request = $this->container[4]['request'];

        $this->assertEquals('http://localhost:9999/api/v2/write?org=my-org-a&bucket=my-bucket&precision=ns',
            strval($request->getUri()));
        $this->assertEquals('h2o_feet,location=coyote_creek level\\ water_level=6.0 6', $request->getBody());
    }

    public function testFlushAllByCloseClient()
    {
        $this->mockHandler->append(new Response(204));

        $this->writeApi->writeOptions->batchSize = 10;

        $this->writeApi->write('h2o_feet,location=coyote_creek level\\ water_level=1.0 1');
        $this->writeApi->write('h2o_feet,location=coyote_creek level\\ water_level=2.0 2');
        $this->writeApi->write('h2o_feet,location=coyote_creek level\\ water_level=3.0 3');

        $this->assertNull($this->mockHandler->getLastRequest());

        $this->client->close();

        $request = $this->mockHandler->getLastRequest();

        $this->assertEquals('http://localhost:9999/api/v2/write?org=my-org&bucket=my-bucket&precision=ns',
            strval($request->getUri()));
        $this->assertEquals("h2o_feet,location=coyote_creek level\\ water_level=1.0 1\n"
            . "h2o_feet,location=coyote_creek level\\ water_level=2.0 2\n"
            . 'h2o_feet,location=coyote_creek level\\ water_level=3.0 3', $request->getBody());
    }
}
