<?php
namespace App\Services\Choice;
use BaconQrCode\{Writer,Common\ErrorCorrectionLevel,Renderer\ImageRenderer,Renderer\Image\SvgImageBackEnd,Renderer\RendererStyle\RendererStyle};
use Illuminate\Validation\ValidationException;
class ReceiptQrCode {
    public function payload(array $details): string {
        return 'User ID: '.(string)($details['user']??'')."\nRegistration: ".(string)($details['reg']??'')."\nName: ".(string)($details['name']??'');
    }
    public function dataUri(array $details): string {
        if (!class_exists(Writer::class)) throw ValidationException::withMessages(['pdf'=>'QR support is missing. Run composer install to restore the locked BaconQrCode dependency.']);
        $renderer=new ImageRenderer(new RendererStyle(300,4),new SvgImageBackEnd);
        $svg=(new Writer($renderer))->writeString($this->payload($details),'UTF-8',ErrorCorrectionLevel::M());
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
