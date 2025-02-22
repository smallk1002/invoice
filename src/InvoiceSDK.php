<?php
/**
 * Name: 发票SDK.
 * User: 董坤鸿
 * Date: 2020/06/23
 * Time: 13:59
 */

namespace Buqiu\Invoice;

use Buqiu\Invoice\PackageInfo;

class InvoiceSDK
{
    const KJFP = 'ECXML.FPKJ.BC.E_INV';
    const DOWNLOAD = 'ECXML.FPMXXZ.CX.E_INV';
    const EMAIL = 'ECXML.EMAILPHONEFPTS.TS.E.INV';
    const INVOICENUMBER = 'ECXML.QY.KYFPSL';

    private static $config = [];
    private static $host = '';
    private static $key = '';
    private $packageInfo = '';

    public function __construct($config)
    {
        self::$config = $config;
        self::$host = $config['HOST'];
        self::$key = $config['KEY'];
        $this->packageInfo = new PackageInfo($config);
    }

    /***
     * 开具发票
     *
     * @param  array  $arr
     * @return \SimpleXMLElement
     */
    public function create(array $arr)
    {
        $data = [];
        if ($arr['invoice_type'] == 2) {
            $data['ghfmc'] = $arr['invoice_title'];
            $data['ghfqylx'] = '01';
        } else {
            $data['ghfmc'] = '个人';
            $data['ghfqylx'] = '03';
        }
        $items = [];
        //查询子项目
        foreach ($arr['items'] as $key => $item) {
            $show_name = $item['name'];
            $items[$key]['XMMC'] = $show_name;
            $items[$key]['XMSL'] = sprintf('%.8f', $item['quantity']);
            $items[$key]['XMDJ'] = sprintf('%.8f', $item['price']);
            $items[$key]['SPBM'] = $item['spbm'];
            $items[$key]['ZXBM'] = $item['zxbm'];
            $items[$key]['XMJE'] = sprintf('%.2f', $item['price'] * $item['quantity']);
            $items[$key]['SL'] = $item['sl'];
            $items[$key]['HSBZ'] = $item['hsbz'];
            $items[$key]['YHZCBS'] = $item['yhzcbs'];
            $items[$key]['ZZSTSGL'] = $item['zzstsgl'];
            $items[$key]['LSLBS'] = $item['lslbs'];

            if ($arr['discount'] && $arr['discount'] != 0.00 && $key == 0) {

                $items[$key]['FPHXZ'] = 2;
                $items[$key]['discount'] = [
                    'XMMC' => $show_name,
                    'XMSL' => '-'.sprintf('%.8f', 1),
                    'FPHXZ' => '1',
                    'XMDJ' => sprintf('%.8f', $arr['discount']),
                    'SPBM' => $item['spbm'],
                    'ZXBM' => $item['id'],
                    'XMJE' => '-'.sprintf('%.2f', $arr['discount']),
                    'SL' => $item['sl'],
                    'HSBZ' => $item['hsbz'],
                ];
            } else {
                $items[$key]['FPHXZ'] = 0;
            }
            if ($key == 0) {
                $data['KPXM'] = $show_name; //kpxm
            }
        }

        $data['items'] = $items;
        $data['mobile'] = isset($arr['mobile']) ? $arr['mobile'] : '';

        $data['KPHJJE'] = sprintf('%.2f', $arr['sum']);
        $data['HJBHSJE'] = sprintf('%.2f', $arr['sum']);
        $data['HJSE'] = sprintf('%.2f', $arr['HJSE']);
        $data['DDH'] = $arr['order_bn'];

        $data['FPQQLSH'] = $arr['FPQQLSH'];
        ///$data['KPXM'] = $arr['KPXM'];
        $data['GHFMC'] = $arr['GHFMC'];
        $data['GHF_SJ'] = $arr['GHF_SJ'];
        $data['GHFQYLX'] = $arr['GHFQYLX'];
        $data['GHF_NSRSBH'] = $arr['GHF_NSRSBH'];
        $data['KPLX'] = $arr['KPLX'];
        $data['CZDM'] = $arr['CZDM'];
        $data['GHF_YHZH'] = isset($arr['GHF_YHZH']) ? $arr['GHF_YHZH'] : "";
        $data['GHF_DZ'] = isset($arr['GHF_DZ']) ? $arr['GHF_DZ'] : "";
        $data['GHF_GDDH'] = isset($arr['GHF_GDDH']) ? $arr['GHF_GDDH'] : "";
        $data['YFP_DM'] = isset($arr['YFP_DM']) ? $arr['YFP_DM'] : "";
        $data['YFP_HM'] = isset($arr['YFP_HM']) ? $arr['YFP_HM'] : '';
        $data['BZ'] = isset($arr['BZ']) ? $arr['BZ'] : '';
        $content = $this->packageInfo->getContent($data);
        $xml = $this->packageInfo->getXml(self::KJFP, $content);

        $response = $this->postCurl(self::$host, $xml);
        $content = simplexml_load_string($response);

        return $content;
    }

    /**
     * 请求方式  POST
     * @param $url
     * @param $params
     * @param  string  $headerArr
     * @return bool|string
     */
    public function postCurl($url, $params, $headerArr = '')
    {
        if ( ! $url) {
            return '请求缺少URL！';
        }

        $headers = array(
            //'content-type:application/json;charset=utf-8',
            'content-type:application/x-www-form-urlencoded;charset=utf-8',

        );

        if (is_array($headerArr) && ! empty($headerArr)) {
            $queryHeaders = array();
            foreach ($headerArr as $k => $v) {
                $queryHeaders[] = $k.':'.$v;
            }
            //print_r($queryHeaders);
            $headers = array_merge($headers, $queryHeaders);
        }

        //$body = json_encode($params);
        $body = $params;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);

        //curl_setopt($ch, CURLOPT_HTTPHEADER,array("Content-Type: application/json;charset=utf-8"));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

        //绕过SSL验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if ( ! empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $return_content = curl_exec($ch);//运行curl
        curl_close($ch);

        return $return_content;

    }

    /**
     * 下载发票
     *
     * @param  array  $arr
     * @return \SimpleXMLElement
     */
    public function download(array $arr)
    {
        $len = strlen($arr['order_bn']);
        $data['lsh'] = str_repeat('0', 20 - $len).$arr['order_bn'];
        $data['PDF_XZFS'] = 3;
        $data['DDH'] = $arr['order_bn'];
        $data['FPQQLSH'] = $arr['FPQQLSH'];

        $content = $this->packageInfo->getDownload($data);
        $xml = $this->packageInfo->getXml(self::DOWNLOAD, $content);

        $response = $this->postCurl(self::$host, $xml);

        $return = simplexml_load_string($response);

        if ($return->returnStateInfo->returnCode[0] == '0000') {
            //PDF_XZFS 1 是pdf内容 必然要解压
            if ($return->Data->dataDescription->zipCode[0] == 1) {
                $content = gzdecode(base64_decode($return->Data->content[0]));
                $rs = openssl_decrypt($content, "des-ede3", str_pad(self::$key, 24, '0'), 1);
                $pdf = simplexml_load_string($rs);

                return $pdf;
            }
        } else {
            //状态有误
            $res['code'] = $return->returnStateInfo->returnCode[0];
            $res['message'] = base64_decode($return->returnStateInfo->returnMessage[0]);

            return $res;
        }

        return $return;
    }

    /**
     * 发送邮件
     *
     * @param  array  $arr
     * @return \SimpleXMLElement
     */
    public function email(array $arr)
    {
        $len = strlen($arr['order_bn']);
        $data['lsh'] = str_repeat('0', 20 - $len).$arr['order_bn'];
        $data['email'] = $arr['email'];
        $data['fp_dm'] = $arr['fp_dm'];
        $data['fp_hm'] = $arr['fp_hm'];
        $data['FPQQLSH'] = $arr['FPQQLSH'];

        $content = $this->packageInfo->getEmail($data);
        $xml = $this->packageInfo->getXml(self::EMAIL, $content);

        $response = $this->postCurl(self::$host, $xml);

        $return = simplexml_load_string($response);


        if ($return->returnStateInfo->returnCode[0] == '0000') {
            //修改状态
            return $return;
        } else {
            echo "\n INVOICE INFO ERROR EMAIL \t {$return->returnStateInfo->returnCode[0]}\t";
        }

        return $return;
    }

    /**
     * 发票剩余数量
     *
     * @return mixed
     */
    public function getInvoiceNumber()
    {
        $content = $this->packageInfo->getInvoiceNumber();
        $xml = $this->packageInfo->getXml(self::INVOICENUMBER, $content);

        $response = $this->postCurl(self::$host, $xml);
        $return = simplexml_load_string($response);

        $res['code'] = (string) $return->returnStateInfo->returnCode[0];
        $res['message'] = base64_decode($return->returnStateInfo->returnMessage[0]);
        if ($return->returnStateInfo->returnCode[0] == '0000') {
            $content = base64_decode($return->Data->content[0]);
            $rs = openssl_decrypt($content, "des-ede3", str_pad(self::$key, 24, '0'), 1);
            $data = strip_tags(trim($rs));
            $res['number'] = (int) trim(explode(' ', $data)['8']);
        } else {
            $res['number'] = '';
        }

        return $res;
    }
}