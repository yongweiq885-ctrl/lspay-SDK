// 接收支付、退款、转账通知。
//
//	go run ./examples/notify_server -addr :8080
//
// 把 https://你的域名/lspay/notify 配为 notify_url。路径不能带查询参数，也不能要求登录。
package main

import (
	"errors"
	"flag"
	"log"
	"net/http"
	"os"

	lspay "lspay.tech/lspay-sdk-go"
)

func main() {
	addr := flag.String("addr", ":8080", "监听地址")
	flag.Parse()

	cfg, err := lspay.LoadConfig(os.Getenv("LSPAY_CONFIG"))
	if err != nil {
		log.Fatal(err)
	}
	client, err := lspay.NewClient(cfg)
	if err != nil {
		log.Fatal(err)
	}

	// 多商户时每个商户一个 Client、一个回调路径，不要按通知里的 mch_no 选密钥。
	http.HandleFunc("/lspay/notify", func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "text/plain; charset=utf-8")
		if err := handle(client, r); err != nil {
			log.Printf("通知处理失败: %v", err)
			w.WriteHeader(http.StatusBadRequest)
			_, _ = w.Write([]byte(lspay.NotifyFailureResponse))
			return
		}
		_, _ = w.Write([]byte(lspay.NotifySuccessResponse))
	})
	log.Printf("listening on %s", *addr)
	log.Fatal(http.ListenAndServe(*addr, nil))
}

// handle 必须在 3 秒内返回。耗时逻辑放到队列里。
func handle(client *lspay.Client, r *http.Request) error {
	form, err := lspay.ReadNotifyForm(r)
	if err != nil {
		return err
	}
	notify, err := client.ParseNotify(form)
	if err != nil {
		return err
	}

	// 业务处理要放在数据库事务里：按单号锁行、核对金额、用 IdempotencyKey 建唯一键去重
	// （重复通知直接返回 ok）、更新状态，提交后才返回 ok。
	switch n := notify.(type) {
	case *lspay.PaymentNotify:
		if !n.IsSucceeded() {
			return nil
		}
		order, err := loadOrder(n.MchOrderNo())
		if err != nil {
			return err
		}
		if err := n.AssertOrder(order.MchOrderNo, order.AmountFen, "cny"); err != nil {
			return err
		}
		log.Printf("支付成功 %s key=%s", n.MchOrderNo(), n.IdempotencyKey())
	case *lspay.RefundNotify:
		log.Printf("退款通知 %s state=%s key=%s", n.MchRefundNo(), n.Value("state"), n.IdempotencyKey())
	case *lspay.DelayTransTransferNotify:
		log.Printf("转账进度 %s %s 已处理 %d 分", n.TaskID(), n.TaskStatus(), n.ProcessedAmount())
	default:
		return errors.New("未知通知类型")
	}
	return nil
}

type localOrder struct {
	MchOrderNo string
	AmountFen  int64
}

// loadOrder 需替换为按单号查询本地订单并加行锁。未接入前一律返回错误，通知会应答 fail。
func loadOrder(mchOrderNo string) (localOrder, error) {
	return localOrder{}, errors.New("示例未接入本地订单存储: " + mchOrderNo)
}
