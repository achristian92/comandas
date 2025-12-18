<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Warrior\Ticketer\Ticketer;

class CheckApiCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consulta una API cada 10 segundos';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Iniciando escuchador para consultar la API cada 20 segundos...');

        // Bucle infinito para ejecutar cada 20 segundos
        while (true) {
            $now = Carbon::now();
            $ticketer = new Ticketer();
            $ticketer->init('network', '192.168.18.65');
            $ticketer->setFechaEmision($now);
            $ticketer->setComprobante('BOLETA');
            $ticketer->setSerieComprobante('B001');
            $ticketer->setNumeroComprobante('000000100');
            $ticketer->setCliente('Edwin Alexander Bautista Villegas');
            $ticketer->setTipoDocumento(1);
            $ticketer->setNumeroDocumento('72462226');
            $ticketer->setDireccion('Jr. Enarte Torres 421 - Santa Lucia');
            $ticketer->setTipoDetalle('DETALLADO');

            $ticketer->addItem("POLLO A LA BRASA", 2, 21.5, false, false);
            $ticketer->printComprobante();
//            try {
//                // Realizar la solicitud a la API
//                $response = Http::get('https://tudominio.com/api/endpoint');
//
//                if ($response->successful()) {
//                    $data = $response->json();
//                    $this->info('Datos recibidos: ' . json_encode($data));
//                } else {
//                    $this->error('Error al realizar la solicitud: ' . $response->status());
//                }
//            } catch (\Exception $e) {
//                $this->error('Excepción al consultar la API: ' . $e->getMessage());
//            }

            // Espera 20 segundos antes de la siguiente iteración
            sleep(5);
        }
    }
}
