// 订单查询。未收到支付通知时，用它按商户订单号补单。
//
//	go run ./examples/query_order -order GO1700000000000
package main

import (
	"context"
	"flag"
	"fmt"
	"log"
	"os"

	lspay "lspay.tech/lspay-sdk-go"
)

func main() {
	orderNo := flag.String("order", "", "商户订单号 mch_order_no")
	flag.Parse()

	cfg, err := lspay.LoadConfig(os.Getenv("LSPAY_CONFIG"))
	if err != nil {
		log.Fatal(err)
	}
	client, err := lspay.NewClient(cfg)
	if err != nil {
		log.Fatal(err)
	}

	resp, err := client.QueryOrder(context.Background(), &lspay.QueryOrderRequest{MchOrderNo: *orderNo})
	if err != nil {
		log.Fatal(err)
	}
	if err := resp.Err(); err != nil {
		log.Fatal(err)
	}
	state, _ := resp.State()
	fmt.Printf("订单 %s 状态 %d，金额 %s 元，已支付: %v\n",
		resp.MchOrderNo(), state, lspay.FenToYuan(resp.Amount()), resp.IsPaid())
}
