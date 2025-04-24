<?php

namespace App\Console;

use App\Jobs\Emails\EmailResendingDocumentsJob;
use App\Jobs\Events\EventSendMailsJob;
use App\Jobs\Events\ProcessEventsMasterJob;
use App\Jobs\ProcessCompanyJsonFiles;
use App\Jobs\ProcessMigratedJSonDataJob;
use App\Jobs\Shipping\CorrectionShippingJob;
use App\Jobs\Shipping\SendCorrectionShippingJob;
use App\Jobs\Test\TestResendDocumentsJob;
use App\Jobs\Test\TestValidateDocumentsJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        // Eventos de recepción
        $schedule->job(new ProcessEventsMasterJob())->everyMinute()->timezone('America/Bogota');
        $schedule->job(new EventSendMailsJob())->everyTwoMinutes()->timezone('America/Bogota');
        // Test de prueba de documentos para habilitación de la API ante la DIAN
        $schedule->job(new TestValidateDocumentsJob())->everyMinute()->timezone('America/Bogota');
        $schedule->job(new TestResendDocumentsJob())->everyMinute()->timezone('America/Bogota');

        $schedule->job(new SendCorrectionShippingJob())->everyMinute()->timezone('America/Bogota');
        // $schedule->job(new ProcessCompanyJsonFiles())->everyMinute()->timezone('America/Bogota');
        $schedule->job(new EmailResendingDocumentsJob())->everyMinute()->timezone('America/Bogota');
        // $schedule->job(new ProcessMigratedJSonDataJob())->everyMinute()->timezone('America/Bogota'); TODO: Uncomment this line when the job is ready
        $schedule->job(new CorrectionShippingJob())->everyTenMinutes()->timezone('America/Bogota');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
