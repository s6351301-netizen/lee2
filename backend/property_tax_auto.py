from playwright.sync_api import sync_playwright

def run_simulation():
    with sync_playwright() as p:
        # 啟動瀏覽器
        browser = p.chromium.launch(headless=False)
        page = browser.new_page()
        
        # 1. 進入目標網頁
        page.goto("https://www.tax.taichung.gov.tw/taxtrial/landTaxTrial/taxTrail/")
        
        # 2. 填寫表單
        page.select_option("select[name='行政區']", label="大甲區")
        page.select_option("select[name='段小段']", label="義水段")
        page.fill("input[name='母號']", "0995")
        page.fill("input[name='子號']", "0000")
        page.fill("input[name='課稅面積']", "8278.29")
        page.fill("input[name='分子']", "36")
        page.fill("input[name='分母']", "1250")
        page.check("input[value='一般用地']")
        
        # 3. 加入清單 (處理確認彈窗)
        page.on("dialog", lambda dialog: dialog.accept())
        page.click("text=加入土地清單")
        
        # 等待資料寫入與重繪
        page.wait_for_timeout(2000)
        
        # 4. 開始試算 (改用定位器以增加穩定度)
        page.locator("text=開始試算").click()
        
        # 處理 "是否還有其他筆土地" 的詢問視窗，選擇 "否"
        page.on("dialog", lambda dialog: dialog.dismiss())
        
        # 5. 【關鍵修正】不等待 URL 跳轉，改為等待頁面出現特定的試算結果表格
        # 我們等待表格中出現 "預估稅額" 這幾個字
        try:
            # 嘗試等待試算結果的表格或標題出現
            page.wait_for_selector("text=預估稅額(元)", timeout=15000)
            print("程式偵測到試算結果已出現！")
        except:
            print("系統未偵測到試算結果，請檢查是否被驗證碼阻擋或資料輸入錯誤。")

if __name__ == "__main__":
    run_simulation()