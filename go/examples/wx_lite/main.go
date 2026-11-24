// 微信小程序内支付（WX_LITE）。服务端下单后，把 pay_info 里的参数原样交给小程序调用 wx.requestPayment。
//
//	go run ./examples/wx_lite -openid oUpF8uMuAJO_M2pxb1Q9zNjWeS6o
package main

import (
	"context"
	"encoding/json"
	"flag"
	"fmt"
	"log"
	"os"
	"time"

	lspay "lspay.tech/lspay-sdk-go"
)

func main() {
	openID := flag.String("openid", "", "小程序用户 openid")
	flag.Parse()
	if *openID == "" {
		log.Fatal("请用 -openid 传入用户 openid")
	}

	cfg, err := lspay.LoadConfig(os.Getenv("LSPAY_CONFIG"))
	if err != nil {
		log.Fatal(err)
	}
	client, err := lspay.NewClient(cfg)
	if err != nil {
		log.Fatal(err)
	}

	resp, err := client.UnifiedOrder(context.Background(), &lspay.UnifiedOrderRequest{
		MchOrderNo:   fmt.Sprintf("GOLITE%d", time.Now().Unix()),
		WayCode:      lspay.WayCodeWxLite,
		Amount:       1,
		Subject:      "测试商品",
		Body:         "小程序支付",
		ChannelExtra: &lspay.UnifiedOrderChannelExtra{OpenID: *openID},
	})
	if err != nil {
		log.Fatal(err)
	}
	if err := resp.Err(); err != nil {
		log.Fatal(err)
	}

	// signType 用返回值（示例为 RSA），不要写死 MD5。
	info := resp.PayInfoMap()
	payment := map[string]interface{}{}
	for _, key := range []string{"timeStamp", "nonceStr", "package", "signType", "paySign"} {
		payment[key] = info[key]
	}
	out, _ := json.MarshalIndent(payment, "", "  ")
	fmt.Println("返回给小程序的 wx.requestPayment 参数:")
	fmt.Println(string(out))
}
