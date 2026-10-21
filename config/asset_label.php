<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Physical sticker stock
    |--------------------------------------------------------------------------
    |
    | The die-cut label loaded in the printer, in millimetres. The print sheet
    | sets @page to exactly this size and draws one sticker per page, so these
    | numbers must match the media size configured in the Zebra ZD230 driver —
    | if the driver says 100mm and the page says 50mm, Chrome shrinks the whole
    | sticker and the QR comes out too small to scan.
    |
    | Measured stock: 50mm wide, 25mm high (the ZD230 driver's 1.969" x 0.984").
    | Set ASSET_LABEL_WIDTH_MM / ASSET_LABEL_HEIGHT_MM in .env to change stock
    | without touching any view.
    |
    */

    'width_mm' => env('ASSET_LABEL_WIDTH_MM', 50),

    'height_mm' => env('ASSET_LABEL_HEIGHT_MM', 25),

    /*
    | Distance kept between the printed border and the die line. A thermal head
    | cannot print on the cut, so the artwork sits inside it.
    */

    'inset_mm' => env('ASSET_LABEL_INSET_MM', 1),

    /*
    | Width of the QR square, including its 4-module quiet zone.
    |
    | 21mm is as large as a 25mm-tall sticker can carry: a signed payload is 45
    | modules across (version 7, alphanumeric mode, error correction M), plus
    | the 4-module quiet zone, which is 3.2 printer dots per module on a 203dpi
    | ZD230. Smaller than that and the modules start merging in the print head.
    */

    'qr_mm' => env('ASSET_LABEL_QR_MM', 21),

];
