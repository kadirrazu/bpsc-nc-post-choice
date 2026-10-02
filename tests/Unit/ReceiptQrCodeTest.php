<?php
namespace Tests\Unit;
use App\Services\Choice\ReceiptQrCode;
use Tests\TestCase;
class ReceiptQrCodeTest extends TestCase {
    public function test_qr_identity_keeps_leading_zeros_and_generates_embedded_svg(): void {
        $qr=new ReceiptQrCode;
        $identity=['user'=>'00001','reg'=>'00001234','name'=>'Rahim Uddin'];
        $this->assertSame("User ID: 00001\nRegistration: 00001234\nName: Rahim Uddin",$qr->payload($identity));
        $uri=$qr->dataUri($identity); $this->assertStringStartsWith('data:image/svg+xml;base64,',$uri);
        $svg=base64_decode(substr($uri,strpos($uri,',')+1),true);
        $xml=new \DOMDocument; $this->assertTrue($xml->loadXML($svg));
        $this->assertSame('svg',$xml->documentElement->localName);
        $this->assertGreaterThan(0,$xml->getElementsByTagName('path')->length);
    }
}
