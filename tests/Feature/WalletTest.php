<?php

namespace Tests\Feature;

use App\Models\WalletPass;
use App\Services\Wallet\AppleWalletProvider;
use App\Services\Wallet\GoogleWalletProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsScenario;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use BuildsScenario, RefreshDatabase;

    private array $tmp = [];

    protected function tearDown(): void
    {
        foreach ($this->tmp as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    /** Certificados autofirmados SOLO para probar la mecánica de empaquetado y firma (no son de Apple). */
    private function fakeAppleCredentials(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        $csr = openssl_csr_new(['commonName' => 'Pass Type ID: pass.test'], $key);
        $cert = openssl_csr_sign($csr, null, $key, 2);
        $p12 = '';
        openssl_pkcs12_export($cert, $p12, $key, 'pw');
        $pem = '';
        openssl_x509_export($cert, $pem);

        $p12File = tempnam(sys_get_temp_dir(), 'p12');
        $wwdr = tempnam(sys_get_temp_dir(), 'ww');
        file_put_contents($p12File, $p12);
        file_put_contents($wwdr, $pem);
        $this->tmp = [$p12File, $wwdr];

        config(['fidelidad.wallet.apple' => [
            'enabled' => true,
            'pass_type_id' => 'pass.test.adn', 'team_id' => 'TEAM123456', 'certificate_path' => $p12File,
            'certificate_password' => 'pw', 'wwdr_path' => $wwdr, 'organization' => 'ADN Publicidad',
        ]]);
    }

    public function test_wallet_buttons_hidden_when_not_configured(): void
    {
        config([
            'fidelidad.wallet.google.issuer_id' => null,
            'fidelidad.wallet.google.service_account_json' => null,
            'fidelidad.wallet.apple.pass_type_id' => null,
        ]);
        $s = $this->makeBusiness();
        $card = $this->makeCard($s['business'], $s['program']);

        $this->get('/tarjeta/'.$card->uuid)->assertOk()->assertDontSee('Añadir a Apple Wallet')->assertDontSee('Añadir a Google Wallet');
        $this->get('/tarjeta/'.$card->uuid.'/apple.pkpass')->assertNotFound();
        $this->get('/tarjeta/'.$card->uuid.'/google')->assertNotFound();
    }

    public function test_apple_pkpass_is_built_and_signed(): void
    {
        $this->fakeAppleCredentials();
        $s = $this->makeBusiness();
        $card = $this->makeCard($s['business'], $s['program']);

        $res = $this->get('/tarjeta/'.$card->uuid.'/apple.pkpass');
        $res->assertOk()->assertHeader('Content-Type', 'application/vnd.apple.pkpass');

        $file = tempnam(sys_get_temp_dir(), 'pk');
        $this->tmp[] = $file;
        file_put_contents($file, $res->getContent());
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($file));
        foreach (['pass.json', 'manifest.json', 'signature', 'strip.png', 'icon.png'] as $name) {
            $this->assertNotFalse($zip->locateName($name), "Falta {$name} en el pkpass");
        }
        $pass = json_decode($zip->getFromName('pass.json'), true);
        $this->assertSame('pass.test.adn', $pass['passTypeIdentifier']);
        $this->assertSame('adnf-'.$card->uuid, $pass['serialNumber']);
        $this->assertNotEmpty($pass['barcodes'][0]['message'] ?? $pass['barcode']['message'] ?? null);
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $this->assertSame(sha1($zip->getFromName('pass.json')), $manifest['pass.json']);
        $this->assertSame('link_opened', WalletPass::where('card_id', $card->id)->first()->status, 'Descargar no equivale a instalar.');
    }

    public function test_apple_web_service_registration_requires_token_and_marks_installed(): void
    {
        $this->fakeAppleCredentials();
        $s = $this->makeBusiness();
        $card = $this->makeCard($s['business'], $s['program']);
        $pass = app(AppleWalletProvider::class)->ensurePass($card);
        $url = '/wallet/apple/v1/devices/dev123/registrations/pass.test.adn/'.$pass->identifier;

        $this->postJson($url, ['pushToken' => 'abc'])->assertStatus(401);
        $this->postJson($url, ['pushToken' => 'abc'], ['Authorization' => 'ApplePass wrong'])->assertStatus(401);
        $this->postJson($url, ['pushToken' => 'abc'], ['Authorization' => 'ApplePass '.$pass->auth_token])->assertStatus(201);
        $this->assertSame('installed_verified', $pass->fresh()->status);

        $this->getJson('/wallet/apple/v1/devices/dev123/registrations/pass.test.adn?passesUpdatedSince=0')
            ->assertOk()->assertJsonPath('serialNumbers.0', $pass->identifier);

        $this->deleteJson($url, [], ['Authorization' => 'ApplePass '.$pass->auth_token])->assertOk();
        $this->getJson('/wallet/apple/v1/devices/dev123/registrations/pass.test.adn')->assertNoContent();
    }

    public function test_google_payloads_use_neutral_card_data(): void
    {
        config(['fidelidad.wallet.google.issuer_id' => '3388000000000000001']);
        $s = $this->makeBusiness();
        $card = $this->makeCard($s['business'], $s['program']);
        $g = app(GoogleWalletProvider::class);

        $this->assertStringStartsWith('3388000000000000001.adnf_prog_', $g->classId($s['program']));
        $obj = $g->objectPayload($card);
        $this->assertSame($g->objectId($card), $obj['id']);
        $this->assertSame($g->classId($s['program']), $obj['classId']);
        $this->assertSame('ADNF:'.$card->qr_token, $obj['barcode']['value']);
    }
}
