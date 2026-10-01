<?php

declare(strict_types=1);

require __DIR__ . '/../app/core/helpers.php';

function upload_expect(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

function expect_upload_rejected(string $path, string $name, int $size): void
{
    try {
        inspect_contract_document_file($path, $name, $size);
    } catch (RuntimeException) {
        return;
    }
    throw new RuntimeException($name . ' should have been rejected.');
}

$tmp = sys_get_temp_dir() . '/simple-crm-contract-doc-' . bin2hex(random_bytes(5));
mkdir($tmp, 0700, true);
try {
    $pdf = $tmp . '/valid.pdf';
    file_put_contents($pdf, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");
    upload_expect(inspect_contract_document_file($pdf, 'contract.pdf', filesize($pdf))['mime'] === 'application/pdf', 'Valid PDF must pass.');

    $png = $tmp . '/valid.png';
    file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
    upload_expect(inspect_contract_document_file($png, 'image.png', filesize($png))['mime'] === 'image/png', 'Valid PNG must pass.');

    $jpg = $tmp . '/valid.jpg';
    file_put_contents($jpg, base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABD/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/EB//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/EB//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/EB//2Q=='));
    upload_expect(inspect_contract_document_file($jpg, 'image.jpg', filesize($jpg))['mime'] === 'image/jpeg', 'Valid JPG must pass.');

    $webp = $tmp . '/valid.webp';
    file_put_contents($webp, "RIFF\x1A\x00\x00\x00WEBPVP8 \x0E\x00\x00\x00\x10\x00\x00\x00\x9D\x01\x2A\x01\x00\x01\x00\x00\x00");
    upload_expect(inspect_contract_document_file($webp, 'image.webp', filesize($webp))['mime'] === 'image/webp', 'Valid WEBP must pass.');

    $php = $tmp . '/payload.php';
    file_put_contents($php, '<?php echo 1;');
    expect_upload_rejected($php, 'payload.php', filesize($php));
    expect_upload_rejected($php, 'payload.pdf', filesize($php));
    expect_upload_rejected($php, 'payload.html', filesize($php));
    expect_upload_rejected($php, 'payload.svg', filesize($php));
    expect_upload_rejected($pdf, 'too-large.pdf', 10 * 1024 * 1024 + 1);

    upload_expect(sanitize_uploaded_filename("../bad\r\nname.pdf") === 'badname.pdf', 'Original filename must remove paths and controls.');
    upload_expect(contract_document_path('../database.php') === null, 'Path traversal must be blocked.');
    upload_expect(contract_document_path('/absolute/file.pdf') === null, 'Absolute paths must be blocked.');
    upload_expect(!str_contains(contract_document_root(), DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR), 'Storage root must remain outside public.');

    $_FILES['missing_document'] = ['error' => UPLOAD_ERR_NO_FILE];
    try {
        upload_contract_document('missing_document');
        upload_expect(false, 'Missing upload must fail gracefully.');
    } catch (RuntimeException $error) {
        upload_expect(str_contains($error->getMessage(), 'الزامی'), 'Missing upload message must be explicit.');
    }

    if (class_exists('ZipArchive')) {
        $docx = $tmp . '/valid.docx';
        $zip = new ZipArchive();
        $zip->open($docx, ZipArchive::CREATE);
        $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $zip->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>');
        $zip->close();
        upload_expect(inspect_contract_document_file($docx, 'valid.docx', filesize($docx))['extension'] === 'docx', 'Valid DOCX must pass structural validation.');
        expect_upload_rejected($docx, 'raw.zip', filesize($docx));
    }
} finally {
    foreach (glob($tmp . '/*') ?: [] as $file) { @unlink($file); }
    @rmdir($tmp);
}

$controller = file_get_contents(__DIR__ . '/../public/index.php');
$helpers = file_get_contents(__DIR__ . '/../app/core/helpers.php');
upload_expect(str_contains($controller, "filename*=UTF-8''") && str_contains($controller, "header('X-Content-Type-Options: nosniff')"), 'Download headers must be safe.');
upload_expect(str_contains($controller, 'contract_document_path('), 'Download must use the guarded path resolver.');
upload_expect(str_contains($helpers, 'function upload_announcement_attachments') && str_contains($helpers, "announcement_attachment_root()"), 'Announcement attachment upload must remain available.');
upload_expect(str_contains($controller, "if (\$action === 'attachment')") && str_contains($controller, 'Announcement::attachment($id)'), 'Protected announcement download must remain available.');

echo "Contract document upload security tests passed." . PHP_EOL;
