// 申请退款，再用返回的退款订单号查询结果。
//
//	go run ./examples/refund -order GO1700000000000 -amount 1
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
	amount := flag.Int64("amount", 1, "退款金额（分）")
	flag.Parse()

	cfg, err := lspay.LoadConfig(os.Getenv("LSPAY_CONFIG"))
	if err != nil {
		log.Fatal(err)
	}
	client, err := lspay.NewClient(cfg)
	if err != nil {
		log.Fatal(err)
	}
	ctx := context.Background()

	// 重试同一笔退款时必须复用同一个 MchRefundNo。
	refundNo := fmt.Sprintf("RF%d", time.Now().Unix())
	refund, err := client.Refund(ctx, &lspay.RefundRequest{
		MchRefundNo:  refundNo,
		MchOrderNo:   *orderNo,
		RefundAmount: *amount,
		RefundReason: "用户申请退款",
		NotifyURL:    cfg.NotifyURL,
	})
	if err != nil {
		log.Fatal(err)
	}
	if err := refund.Err(); err != nil {
		log.Fatal(err)
	}
	fmt.Println("退款订单号:", refund.RefundOrderID())

	query, err := client.QueryRefund(ctx, &lspay.QueryRefundRequest{
		OrderRefundID: refund.RefundOrderID(),
		MchRefundNo:   refundNo,
	})
	if err != nil {
		log.Fatal(err)
	}
	state, _ := query.State()
	fmt.Printf("退款状态 %d，已退款: %v\n", state, query.IsRefunded())
}
