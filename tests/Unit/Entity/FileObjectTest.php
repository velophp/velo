<?php

namespace Tests\Unit\Entity;

use App\Delivery\Entity\FileObject;
use Illuminate\Support\Str;
use Tests\TestCase;

class FileObjectTest extends TestCase
{
    public function test_it_can_be_created_from_constructor()
    {
        $uuid = Str::uuid()->toString();
        $fileObject = new FileObject(
            uuid: $uuid,
            url: 'https://example.com/file.jpg',
            is_previewable: true,
            mime_type: 'image/jpeg',
            extension: 'jpg'
        );

        $this->assertEquals($uuid, $fileObject->uuid);
        $this->assertEquals('https://example.com/file.jpg', $fileObject->url);
        $this->assertTrue($fileObject->is_previewable);
        $this->assertEquals('image/jpeg', $fileObject->mime_type);
        $this->assertEquals('jpg', $fileObject->extension);
    }

    public function test_it_can_be_serialized_to_array()
    {
        $uuid = Str::uuid()->toString();
        $fileObject = new FileObject(
            uuid: $uuid,
            url: 'https://example.com/doc.pdf',
            is_previewable: false,
            mime_type: 'application/pdf',
            extension: 'pdf'
        );

        $array = $fileObject->toArray();

        $this->assertEquals([
            'uuid'           => $uuid,
            'url'            => 'https://example.com/doc.pdf',
            'is_previewable' => false,
            'mime_type'      => 'application/pdf',
            'extension'      => 'pdf',
        ], $array);

        $this->assertEquals($array, $fileObject->jsonSerialize());
        $this->assertEquals(json_encode($array), json_encode($fileObject));
    }
}
