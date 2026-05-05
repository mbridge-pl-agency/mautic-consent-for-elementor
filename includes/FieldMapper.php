<?php
declare(strict_types=1);

namespace WPME;

final class FieldMapper
{
    /** @var array<string, list<string>> */
    private const ALIASES = [
        'firstname' => [ 'firstname', 'name', 'first_name', 'imie' ],
        'lastname'  => [ 'lastname', 'last_name', 'nazwisko' ],
        'phone'     => [ 'phone', 'tel', 'telefon' ],
        'company'   => [ 'company', 'firma' ],
    ];

    /**
     * @param array<string, array{value?: string, raw_value?: string, type?: string}> $fields
     */
    public static function extract_email( array $fields ): ?string
    {
        $value = $fields['email']['value'] ?? '';
        $value = is_string( $value ) ? trim( $value ) : '';
        return $value !== '' ? $value : null;
    }

    /**
     * @param array<string, array{value?: string, raw_value?: string, type?: string}> $fields
     * @return array<string, string>
     */
    public static function map_to_mautic( array $fields ): array
    {
        $email = self::extract_email( $fields );
        if ( $email === null ) {
            return [];
        }

        $out = [ 'email' => $email ];

        foreach ( self::ALIASES as $mautic_key => $candidates ) {
            foreach ( $candidates as $candidate ) {
                $value = $fields[ $candidate ]['value'] ?? '';
                $value = is_string( $value ) ? trim( $value ) : '';
                if ( $value !== '' ) {
                    $out[ $mautic_key ] = $value;
                    break;
                }
            }
        }

        return $out;
    }
}
