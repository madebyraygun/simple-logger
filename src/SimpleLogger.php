<?php
/**
 * Collect brief exceptions and send daily reports
 *
 * @author     Leo Leoncio
 * @author     Ivan Pinheiro
 * @see        https://github.com/leowebguy
 * @copyright  Copyright (c) 2023, leowebguy
 * @license    MIT
 */

namespace leowebguy\simplelogger;

use Craft;
use craft\base\Plugin;
use craft\events\ExceptionEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\helpers\App;
use craft\web\ErrorHandler;
use craft\helpers\Json;
use craft\web\View;
use leowebguy\simplelogger\services\LoggerService;
use yii\base\Event;

class SimpleLogger extends Plugin
{
    // Properties
    // =========================================================================

    public static $plugin;

    public bool $hasCpSection = false;

    public bool $hasCpSettings = false;

    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();
        self::$plugin = $this;

        if (!$this->isInstalled) {
            return;
        }

        $this->setComponents([
            'loggerService' => LoggerService::class
        ]);

        Event::on(
            ErrorHandler::class,
            ErrorHandler::EVENT_BEFORE_HANDLE_EXCEPTION,
            function(ExceptionEvent $event) {
                // How frequently to send reports
                $hours = intval(App::env('LOGGER_HOURS')) ?: 24;


                // Only if active
                if (!App::env('LOGGER_ON')) {
                    return;
                }

                //Discard any status codes between 400 and 499
                if ($event->exception->statusCode >= 400 && $event->exception->statusCode < 500) {
                    return;
                }

                // Write Log Exception
                $this->loggerService->handleException($event->exception);

                $logfile = Craft::$app->path->getLogPath() . '/simplelogger.json';
                $json = @file_get_contents($logfile);
                $records = Json::decode($json);
                $oldest = $records[0];

                $currentTimestamp = time();
                $logTimestamp = strtotime($oldest['time']);

                // Check if there's new data, if it's been at least $hours since the last report
                if (($currentTimestamp - $logTimestamp >= $hours * 60 * 60)) {
                    $this->loggerService->sendReport();
                }
            }
        );

        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            static function(RegisterTemplateRootsEvent $event) {
                $event->roots['_simplelogger'] = __DIR__ . '/templates';
            }
        );

        Craft::info(
            'Simple Logger plugin loaded',
            __METHOD__
        );
    }
}
