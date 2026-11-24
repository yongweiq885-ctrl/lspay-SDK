from setuptools import find_packages, setup

setup(
    name="lspay-sdk",
    version="3.0.0",
    description="立收通 (LSPay) 支付渠道 Python SDK",
    author="LsPay SDK Contributors",
    license="MIT",
    python_requires=">=3.7",
    packages=find_packages(exclude=("examples", "tests")),
    install_requires=["requests>=2.25.0"],
    classifiers=[
        "Programming Language :: Python :: 3",
        "License :: OSI Approved :: MIT License",
    ],
)
