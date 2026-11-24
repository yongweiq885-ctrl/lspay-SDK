// 统一下单：微信扫码（WX_PUB + code_img_url），返回二维码图片地址。
//
//	LSPAY_MCH_NO=... LSPAY_APP_ID=... LSPAY_MD5_KEY=... LSPAY_NOTIFY_URL=https://... go run ./examples/unified_order
//
// 也可以用 LSPAY_CONFIG 指向 JSON 配置文件。会真实请求网关并创建 0.01 元订单。
package main

import (
	"context"
	"fmt"
	"log"
	"os"
	"time"

	lspay "lspay.tech/lspay-sdk-go"
)

func main() {
	cfg, err := lspay.LoadConfig(os.Getenv("LSPAY_CONFIG"))
	if err != nil {
		log.Fatal(err)
	}
	client, err := lspay.NewClient(cfg)
	if err != nil {
		log.Fatal(err)
	}

	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()
	resp, err := client.UnifiedOrder(ctx, &lspay.UnifiedOrderRequest{
		MchOrderNo:   fmt.Sprintf("GO%d", time.Now().UnixNano()/int64(time.Millisecond)),
		WayCode:      lspay.WayCodeWxPub,
		Amount:       1,
		Subject:      "测试商品",
		Body:         "Go SDK 下单",
		ChannelExtra: &lspay.UnifiedOrderChannelExtra{PayDataType: lspay.PayDataTypeCodeImgURL},
	})
	if err != nil {
		// 超时或网络错误时不要直接重试，先用同一单号调订单查询。
		log.Fatal(err)
	}
	if err := resp.Err(); err != nil {
		log.Fatal(err)
	}
	fmt.Println("pay_order_id:", resp.PayOrderID())
	fmt.Println("二维码图片:", resp.PayInfo())
}
