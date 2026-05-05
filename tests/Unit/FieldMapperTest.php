<?php
declare(strict_types=1);

namespace WPME\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPME\FieldMapper;

final class FieldMapperTest extends TestCase
{
    public function test_extracts_email_required(): void
    {
        $fields = [
            'email' => [ 'value' => 'a@b.com', 'raw_value' => 'a@b.com', 'type' => 'email' ],
        ];
        $this->assertSame( 'a@b.com', FieldMapper::extract_email( $fields ) );
    }

    public function test_returns_null_when_email_missing(): void
    {
        $this->assertNull( FieldMapper::extract_email( [] ) );
        $this->assertNull( FieldMapper::extract_email( [ 'email' => [ 'value' => '' ] ] ) );
    }

    public function test_maps_known_fields_to_mautic_contact(): void
    {
        $fields = [
            'email'     => [ 'value' => 'a@b.com' ],
            'firstname' => [ 'value' => 'Anna' ],
            'lastname'  => [ 'value' => 'Kowalska' ],
            'phone'     => [ 'value' => '+48123456789' ],
            'company'   => [ 'value' => 'Acme' ],
            'message'   => [ 'value' => 'unrelated' ],
        ];
        $this->assertSame(
            [
                'email'     => 'a@b.com',
                'firstname' => 'Anna',
                'lastname'  => 'Kowalska',
                'phone'     => '+48123456789',
                'company'   => 'Acme',
            ],
            FieldMapper::map_to_mautic( $fields )
        );
    }

    public function test_falls_back_from_name_to_firstname_when_no_firstname(): void
    {
        $fields = [
            'email' => [ 'value' => 'a@b.com' ],
            'name'  => [ 'value' => 'Jan Kowalski' ],
        ];
        $this->assertSame(
            [ 'email' => 'a@b.com', 'firstname' => 'Jan Kowalski' ],
            FieldMapper::map_to_mautic( $fields )
        );
    }

    public function test_skips_empty_string_values(): void
    {
        $fields = [
            'email' => [ 'value' => 'a@b.com' ],
            'phone' => [ 'value' => '' ],
        ];
        $this->assertSame( [ 'email' => 'a@b.com' ], FieldMapper::map_to_mautic( $fields ) );
    }
}
