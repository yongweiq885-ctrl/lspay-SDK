// 延时分账：对已支付的 division_mode=2 订单做交易确认，把资金分给电子账户。
//
//	go run ./examples/delay_trans -order GO1700000000000 -receiver 350873274291111 -amount 10
package main

import (
	"context"
	"flag"
	"fmt"
	"log"
	"os"
	"time"

	lspay "lspay.tech/lspay-sdk-go"
)

func main() {
	orderNo := flag.String("order", "", "原支付订单的 mch_order_no")
	receiver := flag.String("receiver", "", "接收方电子账户编号 account_no")
	amount := flag.Int64("amount", 0, "分账金额（分）")
	flag.Parse()

	cfg, err := lspay.LoadConfig(os.Getenv("LSPAY_CONFIG"))
	if err != nil {
		log.Fatal(err)
	}
	client, err := lspay.NewClient(cfg)
	if err != nil {
		log.Fatal(err)
	}

	resp, err := client.DelayTransConfirm(context.Background(), &lspay.DelayTransConfirmRequest{
		MchOrderNo: *orderNo,
		OrderID:    fmt.Sprintf("CF%d", time.Now().Unix()),
		ChannelExtra: &lspay.DivisionChannelExtra{DivList: []lspay.DivisionReceiver{
			{TransIn: *receiver, DivAmt: *amount, Description: "订单分账"},
		}},
	})
	if err != nil {
		log.Fatal(err)
	}
	if !resp.IsSuccessful() {
		log.Fatalf("交易确认未成功: %s trans_status=%s %s", resp.ErrorInfo(), resp.TransStatus(), resp.ErrMsg())
	}
	// 交易确认退款要用 confirm_order_id，必须保存。
	fmt.Println("confirm_order_id:", resp.ConfirmOrderID())
	fmt.Println("剩余未确认金额（分）:", resp.UnconfirmAmount())
}
