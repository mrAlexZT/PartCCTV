<?php
// ------
// PartCCTVCore.php
// (c) 2016 m1ron0xFF
// @license: CC BY-NC-SA 4.0
// ------

class PartCCTVCore
{
    protected $PIDLock;	
/*     protected $IF_Shutdown = 0; */
    protected $IF_Restart_Required = 0;
    protected $PartCCTV_ini = array();
    protected $Logger;
    protected $CamLogger;	
	protected $DBH;
    protected $CoreSettings = array();
	protected $Workers = array();
    protected $WorkerPIDs = array();	

    public function __construct()
    {
        pcntl_signal(SIGTERM, array($this, "signalHandler"));
/*         pcntl_signal(SIGCHLD, array($this, "signalHandler")); */

		exec('killall ffmpeg');
		
		if (!file_exists(__DIR__ . '/../PartCCTV.ini')) {
			throw new PartCCTVException("PartCCTV.ini was not found");
		}
        $this->PartCCTV_ini = parse_ini_file(__DIR__ . '/../PartCCTV.ini', true);

		if($this->PartCCTV_ini['monolog_stream']['enabled'] || $this->PartCCTV_ini['monolog_telegram']['enabled']){
			// Main Log
			$this->Logger = new Monolog\Logger('PartCCTV');

			// Cams Log
			$this->CamLogger = new Monolog\Logger('PartCCTV_CAM');
			
			// Register the logger to handle PHP errors and exceptions
			Monolog\ErrorHandler::register($this->Logger);

			$LoggerRef = new \ReflectionClass('Monolog\Logger');
		}

        //StreamHandler
        if ($this->PartCCTV_ini['monolog_stream']['enabled']) {
            $level = $LoggerRef->getConstant($this->PartCCTV_ini['monolog_stream']['log_level']);
            $this->Logger->pushHandler(new Monolog\Handler\StreamHandler(__DIR__ . '/../PartCCTV.log', $level));
            $this->CamLogger->pushHandler(new Monolog\Handler\StreamHandler(__DIR__ . '/../PartCCTV_CAM.log', $level));
        }
        //TelegramHandler
        if ($this->PartCCTV_ini['monolog_telegram']['enabled']) {
            $level = $LoggerRef->getConstant($this->PartCCTV_ini['monolog_telegram']['log_level']);
            $TelegramHandler = new unreal4u\MonologHandler(new unreal4u\TgLog($this->PartCCTV_ini['monolog_telegram']['token']), $this->PartCCTV_ini['monolog_telegram']['user_id'], $level);
            $this->Logger->pushHandler($TelegramHandler);
            $this->CamLogger->pushHandler($TelegramHandler);
        }

        // PID Lock
        if ($this->PartCCTV_ini['core']['run_as_systemd_service']) {
            $this->PIDLock = fopen($this->PartCCTV_ini['core']['PIDLock'], "w+");
            if (flock($this->PIDLock, LOCK_EX)) { // выполняем эксклюзивную блокировку
                ftruncate($this->PIDLock, 0); // очищаем файл
                fwrite($this->PIDLock, getmypid());
            } else {
                throw new PartCCTVException('Не удалось получить блокировку!');
            }
        }
    }

    public function __destruct()
    {
        if ($this->PartCCTV_ini['core']['run_as_systemd_service']) {
            // PID Lock
            fflush($this->PIDLock);        // очищаем вывод перед отменой блокировки
            flock($this->PIDLock, LOCK_UN); // отпираем файл
        }
    }

	private function DBH_initialize()
	{
		//PDO
		$this->Logger->debug('Инициализация БД');
        $this->DBH = new PDO($this->PartCCTV_ini['db']['dsn'], $this->PartCCTV_ini['db']['user'], $this->PartCCTV_ini['db']['password']);
		$this->DBH->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	}
	
	private function CoreSettings_Fetch()
	{
		$this->Logger->debug('Чтение CoreSettings');
        $CoreSettings_raw = $this->DBH->query('SELECT * FROM core_settings');
        $CoreSettings_raw->setFetchMode(PDO::FETCH_ASSOC);
        while ($row = $CoreSettings_raw->fetch()) {
            $this->CoreSettings[$row['param']] = $row['value'];
        }

        if (empty($this->CoreSettings)) {
            throw new PartCCTVException('CoreSettings не может быть пуст!');
        }
	}
	
	private function Tasks_Fetch()
	{	
		$this->Logger->debug('Обновление списка задач для записи');
		$CamSettings_raw = $this->DBH->query('SELECT id FROM cam_list WHERE enabled = 1');
        $CamSettings_raw->setFetchMode(PDO::FETCH_ASSOC);
        while ($row = $CamSettings_raw->fetch()) {
            $this->WorkerPIDs[$row['id']] = null;
        }
	}
	
	private function WorkerPIDs_Starter()
	{
        foreach($this->WorkerPIDs as $id=>$pid) {
			if($pid === null || !posix_getpgid($pid)) {
				//Last Line of cam log to CamLogger, rm log...
				
				$RawCamInfo = $this->DBH->prepare('SELECT source FROM cam_list WHERE enabled = 1 AND id = :id');
				$RawCamInfo->bindParam(':id', $id);
				$RawCamInfo->execute();
				$CamInfo = $RawCamInfo->fetchColumn();
				// $Source, $Handler, $CustomHadnler?
				
				$Arr1 = array('%SOURCE%', '%SEGTIME_MIN%', '%SEGTIME_SEC%', '%REC_PATH%', '%CAM_ID%');
				$Arr2 = array($Source, $this->CoreSettings['segment_time_min'], $this->CoreSettings['segment_time_min'] * 60, $this->CoreSettings['path'], $id);
				switch ($Handler) {

					case 'ffmpeg':
						$Bin_Path = str_replace($Arr1, $Arr2, $this->CoreSettings['ffmpeg_bin']);
						break;

					case 'motion':
						$Bin_Path = str_replace($Arr1, $Arr2, $this->CoreSettings['motion_bin']);
						break;

					case 'custom':
						$Bin_Path = str_replace($Arr1, $Arr2, $CustomHadnler);
						break;
					
				}
			}
			if($pid === null) {
				//Для каждой камеры запускаем ffmpeg 
				$this->CamLogger->debug('Первичный запуск записи id'.$id);
				//Run BinPath..., STDOUT To log
			}
			if(!posix_getpgid($pid)) {
				//Задержка?!?
				
				//Для каждой камеры запускаем ffmpeg 
				$this->CamLogger->debug('Первичный запуск записи id'.$id);
				//Run BinPath...
			}
			//Update $this->WorkerPIDs PID... STDOUT To log
        }	
	}
	
	private function Updater()
	{
		//ТУТ ПЕРЕЗАГРУЗКА КОНФИГА
	}
	
    /**
     * @throws PartCCTVException
     */
    public function run()
    {

        $this->Logger->info('Запуск ядра платформы PartCCTV ' . PartCCTV_Version);
        $this->Logger->info('PID ядра: ' . getmypid());

		DBH_initialize();
		CoreSettings_Fetch();
		Tasks_Fetch();
		
        $ArchiveCollectionTime = 0;
		$WorkerCheckTime = 0;

        $ZMQContext = new ZMQContext();

        //  Socket to talk to clients
        $ZMQResponder = new ZMQSocket($ZMQContext, ZMQ::SOCKET_REP);
        $ZMQResponder->bind('tcp://*:5555');
        $this->Logger->debug('Запущен ZeroMQ сервер');
        while (TRUE) {

            pcntl_signal_dispatch();

			//(Пере)запускаем воркеры
			if((time() - $WorkerCheckTime) >= 10) {
				WorkerPIDs_Starter();
			}
			
            //  Чистим старые записи
            if ((time() - $ArchiveCollectionTime) >= $this->CoreSettings['segment_time_min'] * 60) {
                $this->Logger->debug('Очистка старых записей');
                $ArchiveCollectionTime = time();
                exec('find ' . $this->CoreSettings['path'] . ' -type f -mtime +' . $this->CoreSettings['TTL'] . ' -delete > /dev/null &');
            }

            $ZMQRequest = $ZMQResponder->recv(ZMQ::MODE_DONTWAIT);

            if ($ZMQRequest) {

                $this->Logger->debug('Получен ZMQ запрос: ' . $ZMQRequest);

                $Parsed_Request = json_decode($ZMQRequest, true);

                switch (json_last_error()) {
                    case 'Request_Error_NONE':
                        break;
                    case 'Request_Error_DEPTH':
                        $Request_Error = 'JSON Parser: Достигнута максимальная глубина стека';
                        break;
                    case 'Request_Error_STATE_MISMATCH':
                        $Request_Error = 'JSON Parser: Некорректные разряды или не совпадение режимов';
                        break;
                    case 'Request_Error_CTRL_CHAR':
                        $Request_Error = 'JSON Parser: Некорректный управляющий символ';
                        break;
                    case 'Request_Error_SYNTAX':
                        $Request_Error = 'JSON Parser: Синтаксическая ошибка, некорректный JSON';
                        break;
                    case 'Request_Error_UTF8':
                        $Request_Error = 'JSON Parser: Некорректные символы UTF-8, возможно неверная кодировка';
                        break;
                    default:
                        $Request_Error = 'JSON Parser: Неизвестная ошибка';
                        break;
                }

                if (!isset($Request_Error)) {

                    switch ($Parsed_Request['action']) {

/*                         case 'worker_info':
                            if (isset($Parsed_Request['id'])) {
                                $CamInfo = $this->DBH->prepare('SELECT source FROM cam_list WHERE enabled = 1 AND id = :id');
                                $CamInfo->bindParam(':id', $Parsed_Request['id']);
                                $CamInfo->execute();
                                $Response = $CamInfo->fetchColumn();
                                $CamInfo = null;
                            } else {
                                $Request_Error = 'worker_info: ID is required!';
                            }
                            break;

                        case 'worker_if_shutdown':
                            $Response = $this->IF_Shutdown;
                            break; */

                        case 'core_status':
                            $status = array(
                                'core_version' => PartCCTV_Version,
                                'core_pid' => getmypid(),
                                'restart_required' => $this->IF_Restart_Required,
                                'path' => $this->CoreSettings['path'],
                                'total_space' => round(disk_total_space($this->CoreSettings['path']) / 1073741824),
                                'free_space' => round(disk_free_space($this->CoreSettings['path']) / 1073741824)
                            );
                            $Response = json_encode($status);
                            unset ($status);
                            break;

                        case 'core_workerpids':
                            $Response = json_encode($this->WorkerPIDs);
                            break;

                        case 'core_restart_is_required':
                            $this->IF_Restart_Required = 1;
                            $Response = 'OK';
                            break;

                        case 'core_stop':
                            if ($this->PartCCTV_ini['core']['run_as_systemd_service']) {
                                $Response = 'Action is disabled!';
                            } else {
                                exec('kill ' . getmypid());
                                $Response = 'OK';
                            }
                            break;

/*                         case 'core_restart':
                            if ($this->PartCCTV_ini['core']['run_as_systemd_service']) {
                                // exec('service partcctv restart'); 
                                // $Response = 'Restart OK'; 
								$Response = 'T.B.D.';
                            } else {
                                $Response = 'Not a Systemd Service!';
                            }
                            break; */

                        case 'core_log':
                            $Response_Log = file_get_contents(__DIR__ . '/../PartCCTV.log');
                            break;

                        case 'cam_log':
                            $Response_Log = file_get_contents(__DIR__ . '/../PartCCTV_CAM.log');
                            break;

                        default:
                            $Request_Error = 'Unknown request!';
                            break;
                    }

                }

                if (isset($Request_Error)) {
                    $this->Logger->INFO('Ошибка обработки запроса: ' . $Request_Error);
                    $ZMQResponder->send($Request_Error);
                    unset($Request_Error);
                } elseif (isset($Response)) {
                    $this->Logger->DEBUG('Ответ платформы: ' . $Response);
                    $ZMQResponder->send($Response);
                    unset($Response);
                } elseif (isset($Response_Log)) {
                    $ZMQResponder->send($Response_Log);
                    unset($Response_Log);
                }

            }

/*             // Завершаем ядро при необходимости
            if ($this->IF_Shutdown) {

                // Время начала завершения работы
                if (!isset($shutdown_time)) {
                    $shutdown_time = time();
                }

                //Все дочерние процессы завершены, можно завершаться

                if (count($this->WorkerPIDs) === 0) {
                    $this->Logger->INFO('Завершение работы ядра платформы');
                    exit(0);
                } elseif (time() - $shutdown_time > 60) {
                    // Хьюстон, у нас проблема, прошло больше минуты, а вырубились не все дочерние процессы
                    $this->Logger->EMERGENCY('Аварийное завершение работы платформы: не все воркеры завершены!');
                    exec('killall -s9 php');
                    exit(1);
                }
            } */
			
			usleep(100000);
			
        }
    }

/*     protected function camWorker($id)
    {
        // Создаем дочерний процесс
        // весь код после pcntl_fork() будет выполняться
        // двумя процессами: родительским и дочерним
        $pid = pcntl_fork();
        if ($pid == -1) {
            // Не удалось создать дочерний процесс
            $this->Logger->alert('Could not launch new worker, exiting');
            return FALSE;
        } elseif ($pid) {
            // Этот код выполнится родительским процессом
            $this->WorkerPIDs[$pid] = $id;
        } else {
            // А этот код выполнится дочерним процессом
			
            //Получаем информацию о камере
            $ZMQContext = new ZMQContext();
            $ZMQRequester = new ZMQSocket($ZMQContext, ZMQ::SOCKET_REQ);
            $ZMQRequester->connect('tcp://localhost:5555');
            $ZMQRequester->send(json_encode(array('action' => 'worker_info', 'id' => $id)));
            $worker_info = $ZMQRequester->recv();
			
            $this->CamLogger->info('Запущен воркер id' . $id . ' с PID ' . getmypid());
			
            exec('mkdir ' . $this->CoreSettings['path'] . '/id' . $id);
			
            $attempts = 0;
            $time_to_sleep = 1;
            $time_of_latest_major_fail = time();

            $Arr1 = array('%SOURCE%', '%SEGTIME_MIN%', '%SEGTIME_SEC%', '%REC_PATH%', '%CAM_ID%');
            $Arr2 = array($worker_info, $this->CoreSettings['segment_time_min'], $this->CoreSettings['segment_time_min'] * 60, $this->CoreSettings['path'], $id);
            switch ($this->CoreSettings['default_handler']) {

                case 'ffmpeg':
                    $Bin_Path = str_replace($Arr1, $Arr2, $this->CoreSettings['ffmpeg_bin']);
                    break;

                case 'motion':
                    $Bin_Path = str_replace($Arr1, $Arr2, $this->CoreSettings['motion_bin']);
                    break;

                case 'custom':
                    $Bin_Path = str_replace($Arr1, $Arr2, $this->CoreSettings['custom_bin']);
                    break;

                default:
                    $this->CamLogger->WARNING('Unknown Default Handler ' . $this->CoreSettings['default_handler'] . ', using ffmpeg');
                    $Bin_Path = str_replace($Arr1, $Arr2, $this->CoreSettings['ffmpeg_bin']);
                    break;

            }
            unset($Arr1, $Arr2);
            $this->CamLogger->debug('Bin_Path: ' . $Bin_Path);

            WHILE (TRUE) {

                $Bin_Path_Output = exec($Bin_Path);

                // А может нам пора выключиться?
                $ZMQRequester->send(json_encode(array('action' => 'worker_if_shutdown')));
                if ($ZMQRequester->recv()) {
                    $this->CamLogger->debug('Завершается воркер id' . $id . ' с PID ' . getmypid());
                    exit(0);
                }

                sleep($time_to_sleep);

                // Запись была стабильной больше 15 минут, всё ок
                if (time() - $time_of_latest_major_fail >= 15 * 60) {
                    $time_of_latest_major_fail = time();
                    $attempts = 0;
                    $time_to_sleep = 1;
                    $this->CamLogger->NOTICE('Перезапущена запись с камеры id' . $id . ', выхлоп:' . $Bin_Path_Output);
                } else {
                    // Хьюстон, у нас проблема

                    // Много спать ни к чему
                    if ($time_to_sleep >= 600) {
                        $time_to_sleep = 1;
                    } else {
                        $time_to_sleep = $time_to_sleep * 2;
                    }

                    // 3 неудачи
                    if ($attempts >= 3) {
                        $this->CamLogger->CRITICAL('Не удалось восстановить запись с камеры id' . $id . ' в течение последних 3 попыток! Выхлоп: ' . $Bin_Path_Output);
                        $attempts = 0;
                    } else {
                        ++$attempts;
                        $this->CamLogger->WARNING('Перезапущена запись с камеры id' . $id . ', выхлоп: ' . $Bin_Path_Output);
                    }
                }
            }
        }
    } */

    public function signalHandler($signo, $pid = null, $status = null)
    {
        switch ($signo) {
            case SIGTERM:
                $this->Logger->info('Получен сигнал SIGTERM, начало завершения работы платформы');
/*                 $this->IF_Shutdown = 1; */
                exec('killall ffmpeg');
				exit();
                break;
/*             case SIGCHLD:
                // При получении сигнала от дочернего процесса
                if (!$pid) {
                    $pid = pcntl_waitpid(-1, $status, WNOHANG);
                }
                // Пока есть завершенные дочерние процессы
                while ($pid > 0) {
                    if ($pid && isset($this->WorkerPIDs[$pid])) {
                        if (!$this->IF_Shutdown) {
                            $this->Logger->CRITICAL('Воркер с PID ' . $pid . ' неожиданно завершил работу');
                        } else {
                            $this->Logger->DEBUG('Воркер с PID ' . $pid . ' завершил работу');
                        }
                        // Удаляем дочерние процессы из списка
                        unset($this->WorkerPIDs[$pid]);
                    }
                    $pid = pcntl_waitpid(-1, $status, WNOHANG);
                }
                break; */
            default:
                // все остальные сигналы
                break;
        }
    }
}
