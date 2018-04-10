<?php
/**
 * Database.class.php
 *
 * @Description: ��̨���ݹ���ģ��
 * @Author     : liebert
 * @Date       : 2017/03/10
 */

namespace Org\Util;
use Think\Db;

//���ݵ���ģ��
class DataBase{
    /**
     * �ļ�ָ��
     * @var resource
     */
    private $fp;

    /**
     * �����ļ���Ϣ part - ��ţ�name - �ļ���
     * @var array
     */
    private $file;

    /**
     * ��ǰ���ļ���С
     * @var integer
     */
    private $size = 0;

    /**
     * ��������
     * @var integer
     */
    private $config;

    /**
     * ���ݿⱸ�ݹ��췽��
     * @param array  $file   ���ݻ�ԭ���ļ���Ϣ
     * @param array  $config ����������Ϣ
     * @param string $type   ִ�����ͣ�export - �������ݣ� import - ��ԭ����
     */
    public function __construct($file, $config, $type = 'export'){
        $this->file   = $file;
        $this->config = $config;
    }

    /**
     * ��һ��������д������
     * @param  integer $size д�����ݵĴ�С
     */
    private function open($size){
        if($this->fp){
            $this->size += $size;
            // ����������õı��ݴ�С
            if($this->size > $this->config['part']){
                $this->config['compress'] ? @gzclose($this->fp) : @fclose($this->fp);// �رյ�ǰ�ļ�ָ��
                $this->fp = null; // �����ļ�ָ��
                $this->file['part']++; // ��ż�1
                session('backup_file', $this->file);// д��Ự
                $this->create(); //���´���һ����
            }
        } else {
            $backuppath = $this->config['path'];
            $filename   = "{$backuppath}{$this->file['name']}-{$this->file['part']}.sql";
            if($this->config['compress']){
                $filename = "{$filename}.gz";
                $this->fp = @gzopen($filename, "a{$this->config['level']}");
            } else {
                $this->fp = @fopen($filename, 'a');
            }
            $this->size = filesize($filename) + $size;
        }
    }

    /**
     * д���ʼ����
     * @return boolean true - д��ɹ���false - д��ʧ��
     */
    public function create(){
        $sql  = "-- -----------------------------\n";
        $sql .= "-- Think MySQL Data Transfer \n";
        $sql .= "-- \n";
        $sql .= "-- Host     : " . C('DB_HOST') . "\n";
        $sql .= "-- Port     : " . C('DB_PORT') . "\n";
        $sql .= "-- Database : " . C('DB_NAME') . "\n";
        $sql .= "-- \n";
        $sql .= "-- Part : #{$this->file['part']}\n";
        $sql .= "-- Date : " . date("Y-m-d H:i:s") . "\n";
        $sql .= "-- -----------------------------\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
        return $this->write($sql);
    }

    /**
     * д��SQL���
     * @param  string $sql Ҫд���SQL���
     * @return boolean     true - д��ɹ���false - д��ʧ�ܣ�
     */
    private function write($sql){
        $size = strlen($sql);

        //����ѹ��ԭ���޷������ѹ����ĳ��ȣ��������ѹ����Ϊ50%��
        //һ�����ѹ���ʶ������50%��
        $size = $this->config['compress'] ? $size / 2 : $size;

        $this->open($size); // ��һ����
        return $this->config['compress'] ? @gzwrite($this->fp, $sql) : @fwrite($this->fp, $sql);
    }

    /**
     * ���ݱ�ṹ
     * @param  string  $table ����
     * @param  integer $start ��ʼ����
     * @return boolean        false - ����ʧ��
     */
    public function backup($table, $start){
        //����DB����
        $db = Db::getInstance();

        //���ݱ�ṹ
        if(0 == $start){
            $result = $db->query("SHOW CREATE TABLE `{$table}`");
            $sql  = "\n";
            $sql .= "-- -----------------------------\n";
            $sql .= "-- Table structure for `{$table}`\n";
            $sql .= "-- -----------------------------\n";
            $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $sql .= trim($result[0]['create table']) . ";\n\n";
            if(false === $this->write($sql)){
                return false;
            }
        }

        //��������
        $result = $db->query("SELECT COUNT(*) AS count FROM `{$table}`");
        $count  = $result['0']['count'];

        //���ݱ�����
        if($count){
            //д������ע��
            if(0 == $start){
                $sql  = "-- -----------------------------\n";
                $sql .= "-- Records of `{$table}`\n";
                $sql .= "-- -----------------------------\n";
                $this->write($sql);
            }

            //�������ݼ�¼
            $result = $db->query("SELECT * FROM `{$table}` LIMIT {$start}, 1000");
            foreach ($result as $row) {
                $row = array_map('addslashes', $row);
                $sql = "INSERT INTO `{$table}` VALUES ('" . str_replace(array("\r","\n"),array('\r','\n'),implode("', '", $row)) . "');\n";
                if(false === $this->write($sql)){
                    return false;
                }
            }

            //���и�������
            if($count > $start + 1000){
                return array($start + 1000, $count);
            }
        }

        //������һ��
        return 0;
    }

    /**
     * �����
     * @param  string  $start
     */
    public function import($start){
        //��ԭ����
        $db = Db::getInstance();

        if($this->config['compress']){
            $gz   = gzopen($this->file[1], 'r');
            $size = 0;
        } else {
            $size = filesize($this->file[1]);
            $gz   = fopen($this->file[1], 'r');
        }

        $sql  = '';
        if($start){
            $this->config['compress'] ? gzseek($gz, $start) : fseek($gz, $start);
        }

        for($i = 0; $i < 1000; $i++){
            $sql .= $this->config['compress'] ? gzgets($gz) : fgets($gz);
            if(preg_match('/.*;$/', trim($sql))){
                if(false !== $db->execute($sql)){
                    $start += strlen($sql);
                } else {
                    return false;
                }
                $sql = '';
            } elseif ($this->config['compress'] ? gzeof($gz) : feof($gz)) {
                return 0;
            }
        }

        return array($start, $size);
    }

    /**
     * �������������ڹر��ļ���Դ
     */
    public function __destruct(){
        $this->config['compress'] ? @gzclose($this->fp) : @fclose($this->fp);
    }
}