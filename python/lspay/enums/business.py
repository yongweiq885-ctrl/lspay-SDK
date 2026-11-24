"""Business protocol constants aligned with the PHP SDK."""


class PayDataType:
    CASHIER = "cashier"
    PAY_URL = "pay_url"
    WXAPP = "wxapp"
    ALIAPP = "aliapp"
    YSFAPP = "ysfapp"
    CODE_URL = "code_url"
    CODE_IMG_URL = "code_img_url"
    NONE = "none"

    @classmethod
    def all(cls):
        return [
            cls.CASHIER, cls.PAY_URL, cls.WXAPP, cls.ALIAPP, cls.YSFAPP,
            cls.CODE_URL, cls.CODE_IMG_URL, cls.NONE,
        ]


class OrderState:
    CREATED, PAYING, PAID, FAILED, REVOKED, REFUNDED, CLOSED = range(7)


class RefundState:
    CREATED, REFUNDING, SUCCEEDED, FAILED, CLOSED = range(5)


class QuickPayTransStatus:
    SUCCEEDED = "S"
    PROCESSING = "P"
    FAILED = "F"


class ElectronicAccountTransStatus(QuickPayTransStatus):
    pass


class ElectronicAccountType:
    PERSONAL = 1
    ENTERPRISE = 2
    INDIVIDUAL_BUSINESS = 3

    @classmethod
    def all(cls):
        return [cls.PERSONAL, cls.ENTERPRISE, cls.INDIVIDUAL_BUSINESS]


class ElectronicAccountRelationType:
    SERVICE_PROVIDER = "SERVICE_PROVIDER"
    STORE = "STORE"
    STAFF = "STAFF"
    STORE_OWNER = "STORE_OWNER"
    PARTNER = "PARTNER"
    HEADQUARTER = "HEADQUARTER"
    BRAND = "BRAND"
    DISTRIBUTOR = "DISTRIBUTOR"
    USER = "USER"
    SUPPLIER = "SUPPLIER"
    CUSTOM = "CUSTOM"

    @classmethod
    def all(cls):
        return [
            cls.SERVICE_PROVIDER, cls.STORE, cls.STAFF, cls.STORE_OWNER,
            cls.PARTNER, cls.HEADQUARTER, cls.BRAND, cls.DISTRIBUTOR,
            cls.USER, cls.SUPPLIER, cls.CUSTOM,
        ]


class DelayTransTaskStatus:
    PENDING = "PENDING"
    PROCESSING = "PROCESSING"
    COMPLETED = "COMPLETED"
    FAILED = "FAILED"
    PARTIAL = "PARTIAL"

    @classmethod
    def all(cls):
        return [
            cls.PENDING, cls.PROCESSING, cls.COMPLETED, cls.FAILED, cls.PARTIAL
        ]
