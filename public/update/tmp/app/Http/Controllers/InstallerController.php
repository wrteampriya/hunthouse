<?php

namespace App\Http\Controllers;

use dacoto\EnvSet\EnvSetEditor;
use dacoto\EnvSet\Facades\EnvSet;
use dacoto\LaravelWizardInstaller\Controllers\InstallFolderController;
use dacoto\LaravelWizardInstaller\Controllers\InstallServerController;
use dacoto\LaravelWizardInstaller\Exceptions\CantGenerateKeyException;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Request as RequestFacades;

class InstallerController extends Controller
{
    public function purchaseCodeIndex()
    {
        if ((new InstallServerController)->check() === false || (new InstallFolderController)->check() === false) {
            return redirect()->route('install.folders');
        }

        return view('vendor.installer.steps.purchase-code');
    }

    /**
     * Check if symlink() function is enabled on the server
     */
    public static function isSymlinkEnabled(): bool
    {
        $disabled = explode(',', ini_get('disable_functions'));
        $disabled = array_map('trim', $disabled);

        return ! in_array('symlink', $disabled) && function_exists('symlink');
    }

    /**
     * Override the keys step to handle symlink check
     */
    public function setKeys(Request $request)
    {
        try {
            $envEditor = app(EnvSetEditor::class);
            $envEditor->setKey('APP_URL', $request->input('app_url'));
            Artisan::call('key:generate', ['--force' => true, '--show' => true]);
            if (empty($envEditor->getValue('APP_KEY'))) {
                $envEditor->setKey('APP_KEY', trim(str_replace('"', '', Artisan::output())));
            }
            $envEditor->save();
            if (empty($envEditor->getValue('APP_KEY'))) {
                throw new CantGenerateKeyException;
            }
        } catch (Exception $e) {
            return back()->withErrors($e->getMessage())->withInput();
        }

        // Check symlink and try storage:link
        if (self::isSymlinkEnabled()) {
            try {
                Artisan::call('storage:link', ['--force' => true]);
            } catch (Exception $e) {
                return back()->withErrors('Storage link failed: '.$e->getMessage())->withInput();
            }
        } else {
            // Skip storage:link — user will see warning on the keys page
            // Still proceed with installation
        }

        try {
            foreach (config('installer.commands', []) as $command) {
                Artisan::call($command);
            }
        } catch (Exception $e) {
            return back()->withErrors($e->getMessage())->withInput();
        }

        return redirect()->route('install.finish');
    }

    public function checkPurchaseCode(Request $request)
    {
        try {
            $app_url = (string) url('/');
            $app_url = preg_replace('#^https?://#i', '', $app_url);

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => 'https://validator.wrteam.in/ebroker_validator?purchase_code=' . $request->input('purchase_code') . '&domain_url=' . $app_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => 'GET',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            $response  = curl_exec($curl);
            $curlError = curl_error($curl);
            curl_close($curl);

            if ($response === false || empty($response)) {
                throw new Exception('Could not connect to the validation server. Please check your internet connection. ' . ($curlError ? "cURL error: {$curlError}" : ''));
            }

            $response = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            if ($response['error']) {
                return view('installer::steps.purchase-code', ['error' => $response['message']]);
            }

            EnvSet::setKey('APPSECRET', $request->input('purchase_code'));
            EnvSet::save();

            $envUpdates = [
                'APP_URL' => RequestFacades::root(),
            ];
            updateEnv($envUpdates);

            return redirect()->route('install.database');
        } catch (Exception $e) {
            $values = [
                'purchase_code' => $request->get('purchase_code'),
            ];

            return view('vendor.installer.steps.purchase-code', ['values' => $values, 'error' => $e->getMessage()]);
        }
    }
}
