<?php

class UserTextUtil {

    public function __construct($taskType) {
        if(!empty($taskType)) {

            $outputTextsDir = './output_texts';

            if(!is_dir($outputTextsDir)) {
                mkdir($outputTextsDir, 0700);
            }

            if ($taskType === 'countAverageLineCount') {
                self::executeMethod('countAverageLineCount');
            }

            if ($taskType === 'replaceDates') {
                self::executeMethod('replaceDates');
            }
        } else {
            echo 'Необходимо передать тип задачи в виде аргумента!';
        }
    }

    /**
     * Выполнить метод
     *
     * @return void
     */
    private static function executeMethod ($methodName) {
        if(!empty($methodName)) {
            // Массив пользователей
            $users = self::getUsersData( 'people.csv');

            foreach ($users as $user) {

                // Имя пользователя
                $userName = trim(preg_replace('/[^a-zA-Zа-яА-Я ]/ui', '',$user));

                // ID пользователя
                $userID = preg_replace("/[^0-9]/", '', $user);

                // Текстовые файлы пользователя
                $textFiles = self::getTextFiles('./texts/', "/^({$userID})\-/", 'ctime', 1);

                if (empty($textFiles)) {
                    echo "\r\nТекстовые файлы у пользователя $userName не найдены!\r\n\r\n";
                } else {
                    echo self::$methodName($userName, $textFiles);
                }
            }
        }
    }

    /**
     * Получить массив пользователей
     *
     * @return $data
     */
    private static function getUsersData ($filePath, $fileEncodings = ['cp1251','UTF-8'], $colDelimiter = '', $rowDelimiter = ''){

        if(!file_exists($filePath)) {
            return false;
        }

        $getContent = trim(file_get_contents($filePath));

        $encodedContent = mb_convert_encoding($getContent,'UTF-8', mb_detect_encoding($getContent, $fileEncodings));

        unset($getContent);

        // Определить разделитель
        if(!$rowDelimiter){
            $rowDelimiter = "\r\n";
            if(false === strpos($encodedContent, "\r\n")) {
                $rowDelimiter = "\n";
            }
        }

        $lines = explode($rowDelimiter, trim($encodedContent));
        $lines = array_filter($lines);
        $lines = array_map('trim', $lines);

        // Авто-опредеоить разделитель из двух возможных: ';' или ','
        // Для расчета берем не более 30 строк из файла
        if(!$colDelimiter) {
            $linesArr = array_slice( $lines, 0, 30 );

            // Если в строке нет не одного из разделителей, то значит определить другой разделитель
            foreach($linesArr as $line){
                if(!strpos( $line, ',')) {
                    $colDelimiter = ';';
                }
                if(!strpos($line, ';')) {
                    $colDelimiter = ',';
                }

                if($colDelimiter) {
                    break;
                }
            }

            // Если первый способ не дал результатов, то считаем кол-во разделителей в каждой строке
            // Определить разделитель по количеству одинаковых символов
            if(!$colDelimiter){
                $delimCounts = array(';' => array(), ',' => array());
                foreach($linesArr as $line){
                    $delimCounts[','][] = substr_count($line, ',');
                    $delimCounts[';'][] = substr_count($line, ';');
                }

                // Убрать нули
                $delimCounts = array_map('array_filter', $delimCounts);

                // Кол-во одинаковых значений массива - это потенциальный разделитель
                $delimCounts = array_map('array_count_values', $delimCounts);

                // Берем только максимальные значения в массиве
                $delimCounts = array_map('max', $delimCounts);

                if($delimCounts[';'] === $delimCounts[',']){
                    return array('Не удалось определить разделитель колонок.');
                }
            }
        }

        $data = [];

        foreach($lines as $line){
            $data[] = $line;
        }

        return $data;
    }

    /**
     * Получить текстовые файлы
     *
     * @return $getTextFiles
     */
    private static function getTextFiles($dir, $exp, $how = 'name', $desc = 0) {

        $getTextFiles = array();

        $dh = opendir($dir);

        if ($dh) {
            while (($file = readdir($dh)) !== false) {
                if (preg_match($exp, $file)) {
                    $stat = stat("$dir/$file");
                    $getTextFiles[$file] = ($how == 'name') ? $file : $stat[$how];
                }
            }

            closedir($dh);
            if ($desc) {
                arsort($getTextFiles);
            }
            else {
                asort($getTextFiles);
            }
        }

        $getTextFiles = array_keys($getTextFiles);

        return $getTextFiles;
    }

    /**
     * Для каждого пользователя посчитать среднее количество строк в его текстовых файлах и вывести на экран вместе с именем пользователя.
     *
     * @return $countAverageLineCount
     */
    private static function countAverageLineCount ($userName, $textFiles) {
        if(!empty($textFiles)) {

            $linesArr = [];

            foreach ($textFiles as $textFile) {

                $textFile = "./texts/{$textFile}";

                if(file_exists($textFile)) {

                    $fileArr = file($textFile);

                    $linesArr[] = count($fileArr);
                }
            }

            $avr = ceil(array_sum($linesArr) / count($linesArr));

            $countAverageLineCount = "\r\nИмя пользователя: {$userName}, среднее количество строк в текстовых файлах: $avr\r\n\r\n";

            return $countAverageLineCount;
        }
    }

    /**
     * Поместить тексты пользователей в папку ./output_texts, заменив в каждом тексте даты в формате `dd/mm/yy` на даты в формате `mm-dd-yyyy`. Вывести на экран количество совершенных для каждого пользователя замен вместе с именем пользователя.
     *
     * @return $replaceDates
     */
    private static function replaceDates($userName, $textFiles) {

        if (!empty($userName) && !empty($textFiles)) {

            $counter = 0;

            $pattern = "/\d{2}\/\d{2}\/\d{2}/";

            foreach ($textFiles as $textFile) {

                $texts = "./texts/{$textFile}";
                $outputTexts = "./output_texts/{$textFile}";

                if(file_exists($texts)) {
                    if(!file_exists($outputTexts)) {
                        file_put_contents($outputTexts, file_get_contents($texts));
                    }
                    // Опционально: сравнивать содержимое получаемого файла и обрабатываего файла
                    /** if(file_get_contents($texts) != file_get_contents($outputTexts)) {
                        file_put_contents($outputTexts, file_get_contents($texts));
                    } **/

                    $line = file_get_contents($outputTexts);

                    $line = preg_replace_callback(
                        $pattern,
                        function ($matches) {

                            $date = new DateTime($matches[0]);

                            $matches[0] = $date->format('m-d-Y');

                            return $matches[0];
                        },
                        $line,
                        -1,
                        $count
                    );
                    file_put_contents($outputTexts, $line);
                }
                $counter += (int)$count;
            }
            if ($count > 0) {
                $replaceDates = "\r\nИмя пользователя: {$userName}, количество замен: $counter\r\n\r\n";
                return $replaceDates;
            } else {
                $replaceDates = "\r\nДанных, которые можно изменить для пользователя {$userName} не найдено!\r\n\r\n";
                return $replaceDates;
            }
        }
    }
}