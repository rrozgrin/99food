<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\Cnpj;
use App\ValueObjects\Cpf;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Testes unitários dos Value Objects de documentos fiscais (CPF e CNPJ).
 */
class DocumentsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // CPF
    // -------------------------------------------------------------------------

    #[Test]
    public function deve_criar_cpf_valido(): void
    {
        $cpf = Cpf::of('529.982.247-25');
        $this->assertSame('52998224725', $cpf->digits());
        $this->assertSame('529.982.247-25', $cpf->formatted());
    }

    #[Test]
    public function deve_criar_cpf_sem_formatacao(): void
    {
        $cpf = Cpf::of('52998224725');
        $this->assertSame('52998224725', $cpf->digits());
    }

    #[Test]
    #[DataProvider('cpfInvalidoProvider')]
    public function deve_rejeitar_cpf_invalido(string $cpf): void
    {
        $this->expectException(InvalidArgumentException::class);
        Cpf::of($cpf);
    }

    public static function cpfInvalidoProvider(): array
    {
        return [
            'sequência repetida' => ['111.111.111-11'],
            'dígitos incorretos' => ['529.982.247-26'],
            'muito curto' => ['1234567890'],
            'letras' => ['abc.def.ghi-jk'],
        ];
    }

    #[Test]
    public function try_of_deve_retornar_null_para_cpf_invalido(): void
    {
        $this->assertNull(Cpf::tryOf('000.000.000-00'));
    }

    #[Test]
    public function deve_verificar_igualdade_de_cpfs(): void
    {
        $a = Cpf::of('529.982.247-25');
        $b = Cpf::of('52998224725');
        $this->assertTrue($a->equals($b));
    }

    // -------------------------------------------------------------------------
    // CNPJ
    // -------------------------------------------------------------------------

    #[Test]
    public function deve_criar_cnpj_valido(): void
    {
        $cnpj = Cnpj::of('11.222.333/0001-81');
        $this->assertSame('11222333000181', $cnpj->digits());
        $this->assertSame('11.222.333/0001-81', $cnpj->formatted());
    }

    #[Test]
    public function deve_criar_cnpj_sem_formatacao(): void
    {
        $cnpj = Cnpj::of('11222333000181');
        $this->assertSame('11222333000181', $cnpj->digits());
    }

    #[Test]
    #[DataProvider('cnpjInvalidoProvider')]
    public function deve_rejeitar_cnpj_invalido(string $cnpj): void
    {
        $this->expectException(InvalidArgumentException::class);
        Cnpj::of($cnpj);
    }

    public static function cnpjInvalidoProvider(): array
    {
        return [
            'sequência repetida' => ['00.000.000/0000-00'],
            'dígito incorreto' => ['11.222.333/0001-82'],
            'muito curto' => ['1122233300018'],
        ];
    }

    #[Test]
    public function try_of_deve_retornar_null_para_cnpj_invalido(): void
    {
        $this->assertNull(Cnpj::tryOf('11.111.111/1111-11'));
    }
}
