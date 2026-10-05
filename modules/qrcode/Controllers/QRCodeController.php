<?php

namespace QrCode\Controllers;

use Base\Request;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode as EndroidQrCode;
use Endroid\QrCode\Writer\PngWriter;

use function Helpers\valid_url;
use function Helpers\view;

class QRCodeController
{
    public function index(Request $request)
    {
        $context = [];
        if ($request->has('uri_code')) {
            $context['url'] = $request->input('uri_code');
        }
        return view('qrcode/index.html.twig', $context);
    }

    public function generate_uri_code(Request $request)
    {
        if (!$request->has('uri_code') || !valid_url($request->input('uri_code'))) {
            header('Content-Type: image/png');
            echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQMAAAAl21bKAAAAA1BMVEUAAACnej3aAAAAAXRSTlMAQObYZgAAAApJREFUCNdjYAAAAAIAAeIhvDMAAAAASUVORK5CYII=');
            // Send nothing;
            exit;
        }

        $uri = $request->input('uri_code');

        $writer = new PngWriter();
        $qrcode = new EndroidQrCode(
            data: $uri,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
        );


        $result = $writer->write($qrcode);

        header('Content-Type: ' . $result->getMimeType());
        echo $result->getString();

        exit;
    }
}
