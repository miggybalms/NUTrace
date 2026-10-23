Add-Type -AssemblyName System.Drawing

$dir = 'C:\Users\MSI THIN\Downloads\NUTrace-shots'
$src = Join-Path $dir 'src'

$files = @(
  '02-requests-filter.png',
  '03-assets-overview-v4.png',
  '04-department-assets-v2.png',
  '05-label-settings-v4.png',
  '06-sticker-closeup-v2.png',
  '07-reports-v4.png',
  '08-disposal-forms-v2.png',
  '08b-disposal-form-v2.png',
  '09-service-life-review-v2.png',
  '10-asset-import.png',
  '11-employees-roles.png',
  '11b-employee-import.png',
  '13-qr-scanner.png',
  '15-notifications-v2.png'
)

foreach ($f in $files) {
  $img = [System.Drawing.Bitmap]::FromFile((Join-Path $src $f))
  $c = $img.GetPixel(30, 60)
  $brush = New-Object System.Drawing.SolidBrush($c)
  $g = [System.Drawing.Graphics]::FromImage($img)
  $g.FillRectangle($brush, 0, 0, 100, 34)
  $g.Dispose()
  $brush.Dispose()
  $img.Save((Join-Path $dir $f), [System.Drawing.Imaging.ImageFormat]::Png)
  $img.Dispose()
  Write-Output "fixed $f -> $($c.Name)"
}

$img = [System.Drawing.Bitmap]::FromFile((Join-Path $src '14-employee-request-form.png'))
$rect = New-Object System.Drawing.Rectangle(560, 80, 680, 900)
$crop = $img.Clone($rect, $img.PixelFormat)
$crop.Save((Join-Path $dir '14-employee-request-form.png'), [System.Drawing.Imaging.ImageFormat]::Png)
$crop.Dispose()
$img.Dispose()
Write-Output 'cropped 14-employee-request-form.png'
