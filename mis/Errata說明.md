# 精通 Python 網路開發
https://www.tenlong.com.tw/products/9786263249639
該書的完整範例程式碼：
Mastering-Python-Networking-Fourth-Edition (GitHub 儲存庫)
https://github.com/PacktPublishing/Mastering-Python-Networking-Fourth-Edition

這本書主要是寫給 網管看的
也就是 主要是學習 底程邏輯的程式
去圖書館借書 看到了...
感覺 是跟目前學的前端與後端技能不一樣
反正就是拿範例來玩..  看看能不能整合到現在我的專案裡面

PHP 和 Python 作為圖靈完備（Turing-complete）的程式語言，都可以達成同樣的網路操作功能。然而，在處理網路底層任務（如 Ping、原始 Socket 通訊）時，兩者的生態系統與設計哲學有顯著差異。
21:29 may 為什麼 Python 在網路底層開發較受歡迎？
標準庫豐富： Python 的標準庫提供了 socket、asyncio、scapy 等強大模組，能直接操作 TCP/UDP Socket、建構自定義封包。

非同步支援： 在網路底層開發中（如高併發監控），非同步（Async）能力至關重要，Python 的 asyncio 原生整合度高。

定位不同： Python 是一種通用程式語言，適合撰寫腳本、後端服務、網路自動化工具；PHP 的設計核心是「網頁伺服器請求處理」（Stateless），這使得它在需要長駐記憶體或高頻網路通訊的任務上，需要額外的開發成本。


# Errata and Improvements

## Page 173, Chapter 5
The book stated the Nokia SR Linux default username and password are both admin. This is true prior to release 22.11.1. 
The username for releases after that version is still admin, but the password is set to NokiaSr1!, please see https://containerlab.dev/manual/kinds/srl/.

## CML NX-OSv no longer available in the latest CML
Thank you [Grana2codes](https://github.com/Grana2codes), Paolo G., Jilles C. for pointing out the issue. In the latest CML, NX-OSv is still available for use within CML, but no longer included in teh refplat IOS image (more information [here](https://developer.cisco.com/docs/modeling-labs/#!nx-os/overview)). You can still download the image [here](https://developer.cisco.com/docs/modeling-labs/#!downloading-files-for-cml-installation). Or you can update the image definition from nxosv to nxosv9000 instead. 
