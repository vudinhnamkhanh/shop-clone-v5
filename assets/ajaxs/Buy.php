<?php 
    require_once("../../config/config.php");
    require_once("../../class/verifyEmaill.php");
    require_once("../../config/function.php");


    if(isset($_POST['value']) && isset($_POST['dichvu']) )
    {
        if(!isset($_SESSION['username']))
        {
            msg_error2(lang(86));
        }
        $dichvu = check_string($_POST['dichvu']);
        $value = check_string($_POST['value']);

        $row = $CMSNT->get_row(" SELECT * FROM `dichvu` WHERE `id` = '$dichvu' ");
        $loai = $row['loai'];
        $ten_dv = $row['dichvu'];
        $token = $getUser['token'];
        if(!$row)
        {
            msg_error2(lang(87));
        }
        if($row['display'] != 'SHOW')
        {
            msg_error2(lang(88));
        }
        if($value <= 0)
        {
            msg_error2(lang(89));
        }
        if($value > $row['mua_toi_da'])
        {
            msg_error2(lang(90).' '.$row['mua_toi_da']);
        }
        if($value < $row['mua_toi_thieu'])
        {
            msg_error2(lang(103).' '.$row['mua_toi_thieu']);
        }
        if($CMSNT->num_rows(" SELECT * FROM `taikhoan` WHERE `dichvu` = '$dichvu' AND `trangthai` = 'LIVE' AND `code` IS NULL ") < $value)
        {
            msg_error2(lang(91));
        }
        $giatien = $row['gia'] * $value;
        $giatien = $giatien - $giatien * $getUser['chietkhau'] / 100;
        if($getUser['money'] < $giatien)
        {
            msg_error2(lang(92));
        }
        if($row['check_live'] == 'VIA' || $row['check_live'] == 'CLONE'){
            $data = $CMSNT->get_list(" SELECT * FROM `taikhoan` WHERE `dichvu` = '$dichvu' AND `code` IS NULL AND `trangthai` = 'LIVE' ");
            $i = 0;
            foreach($data as $row1)
            {
                if($i < $value)
                {
                    $tk = explode("|", $row1['chitiet']);
                    if(CheckLiveClone($tk[0]) == 'DIE')
                    {
                        $CMSNT->update("taikhoan", array(
                            'trangthai' => 'DIE'
                        ), " `id` = '".$row1['id']."' ");
                    }
                    else
                    {
                        $i++;
                    }
                }
                else
                {
                    break;
                }
            }
        }
        else if($row['check_live'] == 'GMAIL' || $row['check_live'] == 'HOTMAIL' || $row['check_live'] == 'YAHOO'){
            $data = $CMSNT->get_list(" SELECT * FROM `taikhoan` WHERE `dichvu` = '$dichvu' AND `code` IS NULL AND `trangthai` = 'LIVE' ");
            $i = 0;
            foreach($data as $row1)
            {
                if($i < $value)
                {
                    $tk = explode("|", $row1['chitiet']);
                    if(CheckLiveEmail($row['check_live'], $tk[0]) == 'DIE')
                    {
                        $CMSNT->update("taikhoan", array(
                            'trangthai' => 'DIE'
                        ), " `id` = '".$row1['id']."' ");
                    }
                    else
                    {
                        $i++;
                    }
                }
                else
                {
                    break;
                }
            }
        }
        else if($row['check_live'] == 'BMT'){
            if($CMSNT->num_rows("SELECT * FROM `token` ") == 0)
            {
                msg_error2("H??? th???ng kh??ng th??? check live BM ngay l??c n??y!");
            }
            $data = $CMSNT->get_list(" SELECT * FROM `taikhoan` WHERE `dichvu` = '$dichvu' AND `code` IS NULL AND `trangthai` = 'LIVE' ");
            $i = 0;
            foreach($data as $row1)
            {
                if($i < $value)
                {
                    $tk = explode("|", $row1['chitiet']);
                    if(CheckLiveBM($tk[0]) == 'DIE')
                    {
                        $CMSNT->update("taikhoan", array(
                            'trangthai' => 'DIE'
                        ), " `id` = '".$row1['id']."' ");
                    }
                    else
                    {
                        $i++;
                    }
                }
                else
                {
                    break;
                }
            }
        }
        else{
            $i = $value;
        }
        if($i >= $value)
        {
            if($getUser['money'] < $giatien)
            {
                msg_error2(lang(92));
            }
            $isCheckMoney = $CMSNT->query(" UPDATE `users` SET `money` = `money` - '$giatien' WHERE `username` = '".$getUser['username']."' ");
            if($isCheckMoney)
            {
                /* C???NG CHI TI??U */
                $CMSNT->cong("users", "used_money", $giatien, " `username` = '".$getUser['username']."' ");

                $getMoneyUser = $CMSNT->get_row("SELECT * FROM `users` WHERE `token` = '".$token."' ")['money'];
                if ($getMoneyUser < 0)
                {
                    msg_error(lang(93), "", 2000);
                }
                $ma_giaodich = random("QWERTYUIOPASDFGHJKLZXCVBNM0123456789", 4).time();
                /* C???P NH???T D??NG TI???N */
                $CMSNT->insert("dongtien", array(
                    'sotientruoc' => $getUser['money'],
                    'sotienthaydoi' => $giatien,
                    'sotiensau' => $getUser['money'] - $giatien,
                    'thoigian' => gettime(),
                    'noidung' => 'Thanh to??n ????n h??ng (#'.$ma_giaodich.')',
                    'username' => $getUser['username']
                ));
                /* C???P NH???T CLONE */
                $CMSNT->update_value("taikhoan", array(
                    'code'          => $ma_giaodich,
                    'thoigianmua'   => gettime()
                ), " `dichvu` = '$dichvu' AND `code` IS NULL AND `trangthai` = 'LIVE'", $value); 
                /* T???O ????N H??NG */
                $CMSNT->insert("orders", array(
                    'code'      => $ma_giaodich,
                    'username'  => $_SESSION['username'],
                    'seller'    => $row['username'],
                    'dichvu'    => $ten_dv,
                    'loai'      => $loai,
                    'soluong'   => $value,
                    'sotien'    => $giatien,
                    'ip'        => myip(),
                    'thoigian'  => gettime(),
                    'time'      => time()
                ));
                /* TH??M NH???T K?? */
                $CMSNT->insert("logs", [
                    'username'  => $getUser['username'],
                    'content'   => 'Thanh to??n ????n h??ng #'.$ma_giaodich,
                    'createdate'=> gettime(),
                    'time'      => time()
                ]);

                /* X??? L?? HOA H???NG CHO CTV */
                if($CMSNT->site('status_ref') == 'ON')
                {
                    if($getUser['ref'] != NULL)
                    {
                        $getRef = $CMSNT->get_row("SELECT * FROM `users` WHERE `id` = '".$getUser['ref']."' ");
                        if($getRef)
                        {
                            $hoahong = $giatien * $CMSNT->site('ck_ref') / 100;
                            /* C???NG HOA H???NG */
                            $CMSNT->cong("users", "money", $hoahong, " `username` = '".$getRef['username']."' ");
                            $CMSNT->cong("users", "ref_money", $hoahong, " `username` = '".$getRef['username']."' ");
                            /* C???P NH???T D??NG TI???N */
                            $CMSNT->insert("dongtien", array(
                                'sotientruoc' => $getRef['money'],
                                'sotienthaydoi' => $hoahong,
                                'sotiensau' => $getRef['money'] + $hoahong,
                                'thoigian' => gettime(),
                                'noidung' => 'Hoa h???ng t??? b???n b?? ('.$getUser['username'].')',
                                'username' => $getRef['username']
                            ));
                        }
                    }
                }
                msg_success(lang(94), BASE_URL("History"), 1000);
            }
            else
            {
                msg_error2(lang(92));
            }
        }
        else
        {
            msg_error2(lang(91));
        }
    }