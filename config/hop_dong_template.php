<?php

return [
    'blocks' => [

        // ================== TRANG BÌA ==================
        [
            'key'   => 'COVER_TITLE',
            'label' => 'Trang bìa – Tiêu đề',
            'text'  => <<<'TEXT'
HỢP ĐỒNG CUNG CẤP DỊCH VỤ
TEXT,
        ],

        [
            'key'   => 'COVER_CONTRACT_NO',
            'label' => 'Trang bìa – Số HĐ',
            'text'  => <<<'TEXT'
Số: {SO_HOP_DONG}
TEXT,
        ],

        [
            'key'   => 'COVER_PARTIES',
            'label' => 'Trang bìa – Hai Bên',
            'text'  => <<<'TEXT'
CÔNG TY {TEN_BEN_A}

VÀ

CÔNG TY {TEN_BEN_B}
TEXT,
        ],

        [
            'key'   => 'COVER_DATE_LINE',
            'label' => 'Trang bìa – Ngày',
            'text'  => <<<'TEXT'
{NGAY_HD_TEXT}
TEXT,
        ],

        // =============== LỜI MỞ ĐẦU / CĂN CỨ ===============
        [
            'key'   => 'PREAMBLE',
            'label' => 'Lời mở đầu (Căn cứ pháp lý)',
            'text'  => <<<'TEXT'
CỘNG HOÀ XÃ HỘI CHỦ NGHĨA VIỆT NAM
Độc lập – Tự do – Hạnh Phúc
----oo0oo----
HỢP ĐỒNG CUNG CẤP DỊCH VỤ
(Số: {SO_HOP_DONG})
-	Căn cứ Bộ luật Dân sự năm 2015;
-	Căn cứ Luật Thương mại 2005; 
-	Căn cứ Luật Quảng Cáo 2012;
-	Căn cứ khả năng và nhu cầu của hai Bên.
Hôm nay, {NGAY_HD_TEXT}, tại {DIA_CHI_BEN_B}, đại diện hai Bên gồm có:
TEXT,
        ],

        // =============== GIỚI THIỆU BÊN A ===============
        [
            'key'   => 'PARTY_A_BLOCK',
            'label' => 'Giới thiệu Bên A',
            'text'  => <<<'TEXT'
BÊN A: {TEN_BEN_A}
-	Địa chỉ : {DIA_CHI_BEN_A}    
-	 Mã số thuế (Tax code): {MST_BEN_A}
-	Đại điện: {DAI_DIEN_BEN_A}  -            Chức vụ: {CHUC_VU_BEN_A}
TEXT,
        ],

        // =============== GIỚI THIỆU BÊN B ===============
        [
            'key'   => 'PARTY_B_BLOCK',
            'label' => 'Giới thiệu Bên B',
            'text'  => <<<'TEXT'
BÊN B: {TEN_BEN_B}
Địa chỉ: {DIA_CHI_BEN_B} 
MST: {MST_BEN_B}
Tài khoản: {TAI_KHOAN_BEN_B} 
Đại diện là: {DAI_DIEN_BEN_B}	Chức vụ: {CHUC_VU_BEN_B}
(Bên A và Bên B sau đây được gọi chung là “các Bên” hoặc “hai Bên” và được gọi riêng là “Bên”).
Sau khi bàn bạc và thống nhất, hai Bên đồng ý ký kết Hợp Đồng Dịch Vụ (“Hợp đồng”) với các điều khoản dưới đây:
TEXT,
        ],

        // =============== ĐIỀU 1 ===============
        [
            'key'   => 'ARTICLE1_BODY',
            'label' => 'Điều 1 – Nội dung Hợp đồng',
            'text'  => <<<'TEXT'
1.1.	Bên A chỉ định Bên B cung cấp dịch vụ và tổ chức chương trình “Sự kiện” cho Bên A với chi tiết hạng mục theo Khoản 1.3 Điều này. 
1.2.	Thời gian và địa điểm như sau:
-	Thời gian: {THOI_GIAN_SU_KIEN_TEXT}	
-	Thời gian lắp đặt (setup): {THOI_GIAN_SETUP_TEXT}
-	Địa điểm tổ chức sự kiện: {DIA_DIEM_SU_KIEN}

1.3.	Chi tiết các hạng mục Bên B thực hiện:
(Bảng A. CƠ SỞ VẬT CHẤT, B. CƠ SỞ VẬT CHẤT, D. CHI PHÍ KHÁC, E. CHI PHÍ QUẢN LÝ… sẽ được trình bày ở bảng hạng mục phía dưới.)
TEXT,
        ],

        // =============== ĐIỀU 2 ===============
        [
            'key'   => 'ARTICLE2_BODY',
            'label' => 'Điều 2 – Giá trị HĐ & Thanh toán',
            'text'  => <<<'TEXT'
2.1.	Tổng giá trị Hợp đồng (đã bao gồm VAT) là: {TONG_SAU_VAT} VNĐ (Bằng chữ:{TONG_SAU_VAT_TEXT})
Chi phí này không bao gồm các chi phí phát sinh khác không được liệt kê tại Khoản 1.3 Điều 1 nêu trên. Trong trường hợp nếu có phát sinh các chi phí tăng thêm hoặc giảm tương ứng do sự thay đổi này, thì phần chi phí này sẽ được tính tương ứng vào giá trị thanh lý của Hợp đồng khi đã có sự đồng ý của Bên B trước khi thực hiện.
2.2.	Thời gian thanh toán: 
-	Đợt 1: Bên A thanh toán  giá trị Hợp đồng {DOT1_PERCENT}% là {DOT1_AMOUNT} VNĐ (Bằng chữ: {DOT1_AMOUNT_TEXT}) ngay sau khi hai Bên ký Hợp đồng và Bên A đã nhận được toàn bộ bản chính Hợp đồng này do Bên B ký và đóng dấu hợp lệ kèm theo đề nghị thanh toán.
-	Đợt 2: Bên A thanh toán  giá trị Hợp đồng còn lại {DOT2_PERCENT}% là {DOT2_AMOUNT} VNĐ (Bằng chữ: {DOT2_AMOUNT_TEXT}) trong vòng 7 (bảy) ngày sau khi chương trình kết thúc và hai Bên ký thanh lý Hợp đồng với điều kiện Bên A đã nhận được đầy bộ hồ sơ đề nghị thanh toán hợp lệ gồm: Biên bản thanh lý Hợp đồng/Biên bản nghiệm thu do Bên B ký và đóng dấu, đề nghị thanh toán kèm hóa đơn tài chính hợp lệ tương ứng với giá trị thanh toán.
2.3.	Phương thức thanh toán: Chuyển khoản theo thông tin tài khoản được quy định tại phần đầu Hợp đồng.
TEXT,
        ],

        // =============== ĐIỀU 3 ===============
        [
            'key'   => 'ARTICLE3_BODY',
            'label' => 'Điều 3 – Nghĩa vụ & quyền của Bên A',
            'text'  => <<<'TEXT'
3.1.	Bên A có nghĩa vụ sau đây:
-	Có trách nhiệm phối hợp với Bên B để chương trình thành công tốt đẹp;
-	Cử đại diện giám sát thi công và làm việc trực tiếp với đại diện Bên B;
-	Có trách nhiệm giữ gìn an ninh trật tự suốt quá trình hoạt động;
-	Không sử dụng trang thiết bị của Bên B sai mục đích đã thỏa thuận;
-	Thanh toán đầy đủ chi phí theo như thỏa thuận. Nếu thanh toán trễ Bên A mà không có lý do chính đáng và thông báo cho Bên B trong một khoản thời gian hợp lý thì sẽ chịu lãi suất 0.03%/ngày (không chấm không ba phần trăm trên ngày) theo giá trị thanh toán bị vi phạm nhưng tổng không quá 08% (tám phần trăm) giá trị vi phạm;
-	Khi có sự cố xảy ra, Bên A phải báo cho Bên B để kịp thời giải quyết;
-	Cung cấp cho Bên B thông tin, tài liệu và các phương tiện cần thiết để thực hiện công việc, nếu có thoả thuận hoặc việc thực hiện công việc đòi hỏi.
3.2.	Bên A có quyền sau đây:
-	Yêu cầu Bên B thực hiện công việc theo đúng chất lượng, số lượng, thời hạn, địa điểm và các thoả thuận khác theo Hợp đồng;
-	Trong trường hợp Bên B vi phạm nghiêm trọng nghĩa vụ thì Bên A có quyền đơn phương chấm dứt thực hiện Hợp đồng và yêu cầu bồi thường thiệt hại.
TEXT,
        ],

        // =============== ĐIỀU 4 ===============
        [
            'key'   => 'ARTICLE4_BODY',
            'label' => 'Điều 4 – Nghĩa vụ & quyền của Bên B',
            'text'  => <<<'TEXT'
4.1.	Bên B có nghĩa vụ sau đây:
-	Thi công lắp đặt và cung cấp các dịch vụ, sản phẩm đúng chất lượng, số lượng, thời hạn, địa điểm và các thoả thuận khác theo Hợp đồng;
-	Chịu trách nhiệm về chất lượng và số lượng theo bảng danh mục kèm theo tại Khoản 1.3 Điều 1 Hợp đồng này;
-	Cử người đại diện chỉ huy trực tiếp, thường xuyên liên lạc với Bên A;
-	Có trách nhiệm phối hợp với Bên A để chương trình thành công tốt đẹp;
-	Không được giao cho người khác thực hiện thay công việc, nếu không có sự đồng ý của Bên A;
-	Bảo quản và phải giao lại cho Bên A tài liệu và phương tiện được giao sau khi hoàn thành công việc;
-	Báo ngay cho Bên A về việc thông tin, tài liệu không đầy đủ, phương tiện không bảo đảm chất lượng để hoàn thành công việc;
-	Giữ bí mật thông tin mà mình biết được trong thời gian thực hiện công việc, nếu có thoả thuận hoặc pháp luật có quy định;
-	Nếu lắp đặt thiết bị không đúng số lượng theo Hợp đồng thì sẽ nghiệm thu và thanh toán theo thực tế của thiết bị;
-	Bồi thường thiệt hại cho Bên A, nếu làm mất mát, hư hỏng tài liệu, phương tiện được giao hoặc tiết lộ bí mật thông tin;
-	Bên B cam kết và chịu trách nhiệm pháp lý về nguồn gốc của hàng hóa hoặc dịch vụ bán/cung cấp cho Bên A là hợp pháp và hợp lệ và tuân thủ các điều kiện về vệ sinh an toàn thực phẩm, bảo quản các thực hiện, thức ăn do Bên B cung cấp vệ sinh, sạch sẽ. 
-	Trường hợp xảy ra bất kỳ sự cố nào do việc cung cấp dịch vụ của Bên B gây ra cho Bên A và/hoặc Bên thứ ba thì Bên B có nghĩa vụ chịu toàn bộ trách nhiệm và bồi thường toàn bộ các thiệt hại phát sinh (nếu có).
-	Bên B chịu trách nhiệm khai báo thuế và nộp thuế đúng và đầy đủ theo quy định Nhà Nước. Bên B phải chịu hoàn toàn trách nhiệm trước pháp luật về các hành vi trốn thuế hoặc gian lận thuế.
4.2.	Bên B có quyền sau đây:
-	Yêu cầu Bên A cung cấp đầy đủ thông tin về nội dung chương trình nhằm đảm bảo đúng kế hoạch về các hạng mục kỹ thuật và nhân sự.
-	Được thay đổi điều kiện dịch vụ vì lợi ích của Bên A, mà không nhất thiết phải chờ ý kiến của Bên A, nếu việc chờ ý kiến sẽ gây thiệt hại cho Bên A, nhưng phải báo ngay cho Bên A và đảm bảo việc thay đổi điều kiện dịch vụ không làm giảm chất lượng dịch vụ. 
-	Yêu cầu Bên A trả tiền dịch vụ.
TEXT,
        ],

        // =============== ĐIỀU 5 ===============
        [
            'key'   => 'ARTICLE5_BODY',
            'label' => 'Điều 5 – Rủi ro & bất khả kháng',
            'text'  => <<<'TEXT'
5.1.	Rủi ro là nguy cơ ảnh hưởng tiêu cực đến việc thực hiện Hợp đồng.
5.2.	Bất khả kháng là sự kiện xảy ra một cách khách quan không thể lường trước được và không thể khắc phục được mặc dù đã áp dụng mọi biện pháp cần thiết và khả năng cho phép bao gồm nhưng không giới hạn: động đất, bão, lũ, lụt, lốc, sóng thần, lở đất hay hoạt động núi lửa, chiến tranh, dịch bệnh và các trường hợp bất khả kháng khác.
5.3.	Nếu xảy ra sự kiện bất khả kháng, Bên bị ảnh hưởng sẽ không phải chịu trách nhiệm do không/chậm thực hiện Hợp đồng, với điều kiện là Bên bị ảnh hưởng đã thông báo bằng văn bản chậm nhất 03 (ba) ngày kể từ ngày xảy ra sự kiện bất khả kháng cho Bên còn lại và áp dụng mọi biện pháp ngăn chặn hoặc khắc phục hậu quả do sự kiện bất khả kháng gây ra. Các Bên tự chịu phần thiệt hại của mình do trường hợp bất khả kháng gây ra.
TEXT,
        ],

        // =============== ĐIỀU 6 ===============
        [
            'key'   => 'ARTICLE6_BODY',
            'label' => 'Điều 6 – Cam kết chống hối lộ, hoa hồng, chi giảm giá',
            'text'  => <<<'TEXT'
6.1.	Bên B hiểu rằng Bên A coi trọng uy tín của mình trong việc giữ gìn đạo đức kinh doanh, trung thực và khách quan về các vấn đề tài chính. Vì mục đích này, Bên B cam kết không (i) Hối lộ/đưa hoa hồng/chi giảm giá/chi lại quả dưới hình thức tiền/quà/sản phẩm/dịch vụ hoặc dưới bất kỳ hình thức nào khác cho nhân viên của Bên A nhằm ảnh hưởng đến quyết định lựa chọn nhà cung cấp; hoặc (ii) Bỏ qua trách nhiệm thông báo cho Bên A về mọi biểu hiện liên quan đến hối lộ của nhân viên của Bên A trong quá trình đàm phán, ký kết và thực hiện Hợp đồng.
6.2.	Đối với bất kỳ vi phạm các nghĩa vụ quy định tại điều khoản trên đây, Bên B đồng ý rằng Bên A được quyền đơn phương:
-	Chấm dứt Hợp đồng mà không cần báo trước và thanh toán bất kỳ khoản bồi thường/tiền phạt nào cho Bên B;
-	Yêu cầu Bên B hoàn trả đầy đủ giá trị Hợp đồng đã được Bên A thanh toán trước cho Bên A;
-	Và yêu cầu Bên B thanh toán một khoản tiền phạt tương đương 8% tổng giá trị Hợp đồng.
TEXT,
        ],

        // =============== ĐIỀU 7 ===============
        [
            'key'   => 'ARTICLE7_BODY',
            'label' => 'Điều 7 – Bồi thường thiệt hại & phạt vi phạm',
            'text'  => <<<'TEXT'
7.1.	Nếu một trong hai Bên đơn phương chấm dứt Hợp đồng trái pháp luật hoặc/bao gồm việc không có lý do chính đáng và không thông báo trước cho Bên còn lại biết, thì phải đền bù 50% tổng giá trị Hợp đồng.
7.2.	Trong trường hợp Bên B không thể thực hiện được chương trình hoặc các dịch vụ đã được thỏa thuận trong thời gian quy định của Hợp đồng này mà không phải do trường hợp bất khả kháng theo Điều 5 thì Bên B phải bồi thường 100% số tiền mà Bên A đã thanh toán cho Bên B. Đồng thời, thanh toán các chi phí thiệt hại trên thực tế (nếu có) cho Bên A.
TEXT,
        ],

        // =============== ĐIỀU 8 ===============
        [
            'key'   => 'ARTICLE8_BODY',
            'label' => 'Điều 8 – Giải quyết tranh chấp',
            'text'  => <<<'TEXT'
Hai Bên cam kết thực hiện đầy đủ các điều khoản trong Hợp đồng này. Mọi thay đổi có phát sinh trong quá trình thực hiện nếu nảy sinh ra các vấn đề vướng mắc thì hai Bên cùng bàn bạc thống nhất tìm cách giải quyết trên tinh thần thương lượng và hợp tác có tình có lý. Bất cứ tranh chấp nào phát sinh từ hoặc liên quan đến việc thực hiện Hợp đồng mà hai Bên không tự giải quyết thì một trong hai Bên có quyền đưa tranh chấp ra Tòa án có thẩm quyền để yêu cầu giải quyết.
TEXT,
        ],

        // =============== ĐIỀU 9 ===============
        [
            'key'   => 'ARTICLE9_BODY',
            'label' => 'Điều 9 – Điều khoản chung',
            'text'  => <<<'TEXT'
9.1.	Hợp đồng này chấm dứt khi:
-	Các Bên đã hoàn thành xong nghĩa vụ và tiến hành ký kết thanh lý Hợp đồng;
-	Hai Bên chấm dứt Hợp đồng trước thời hạn;
-	Một trong các Bên vi phạm Hợp đồng thì Bên bị vi phạm có quyền đơn phương chấm dứt Hợp đồng;
-	Theo quy định của pháp luật.
9.2.	Hợp đồng này có hiệu lực kể từ ngày ký. Trong quá trình thực hiện Hợp đồng, nếu có thay đổi, hai Bên cùng bàn bạc giải quyết trên tinh thần hợp tác. Mọi bổ sung, sửa đổi Hợp đồng này đều phải được hai Bên thống nhất thỏa thuận và thực hiện bằng văn bản. Văn bản sửa đổi, bổ sung Hợp đồng không thể tách rời và có hiệu lực như Hợp đồng.
9.3.	Bảng báo giá và bảng thiết kế đã được Bên A chấp thuận là một phần không tách rời của Hợp đồng này.
9.4.	Hợp đồng này được lập thành 02 (hai) bản, mỗi bên giữ 01 (một) bản để thực hiện và có giá trị pháp lý như nhau.
TEXT,
        ],

        // =============== CHỮ KÝ ===============
        [
            'key'   => 'SIGNATURE_BLOCK',
            'label' => 'Khối chữ ký',
            'text'  => <<<'TEXT'
ĐẠI DIỆN BÊN A                                                           ĐẠI DIỆN BÊN B
   



        (Ông) {DAI_DIEN_BEN_A}                                                  (Ông) {DAI_DIEN_BEN_B}
TEXT,
        ],

    ],
];
