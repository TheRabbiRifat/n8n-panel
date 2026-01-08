from playwright.sync_api import sync_playwright

def verify_orphans():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        BASE_URL = "http://localhost:8080"

        # Login as Admin
        page.goto(f"{BASE_URL}/login")
        page.fill('input[name="email"]', 'admin@example.com')
        page.fill('input[name="password"]', 'password')
        page.click('button[type="submit"]')
        page.wait_for_url(f"{BASE_URL}/dashboard")

        # Navigate to Orphans
        page.goto(f"{BASE_URL}/containers/orphans")
        page.screenshot(path="/home/jules/verification/orphans.png")
        print("Captured orphans.png")

        browser.close()

if __name__ == "__main__":
    verify_orphans()
