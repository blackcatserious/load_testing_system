#!/usr/bin/env python3
import ftplib
import os

def deploy_fixed_frontend():
    """Deploy the fixed frontend with corrected metrics mapping to ftc-compliance.us"""
    
    ftp_host = "ftc-compliance.us"
    ftp_user = "ftcceelg"
    ftp_pass = "JzS2sqqCKJJR"
    
    try:
        print("🔧 Connecting to ftc-compliance.us to deploy fixed frontend...")
        ftp = ftplib.FTP(ftp_host)
        ftp.login(ftp_user, ftp_pass)
        
        print("📁 Navigating to public_html...")
        ftp.cwd('public_html')
        
        dist_path = '/home/ubuntu/repos/load_testing_system/frontend_src/dist'
        
        index_file = os.path.join(dist_path, 'index.html')
        if os.path.exists(index_file):
            print("📄 Uploading fixed index.html...")
            with open(index_file, 'rb') as f:
                ftp.storbinary('STOR index.html', f)
            print("✅ index.html uploaded successfully")
        else:
            print("❌ index.html not found in dist folder")
            return False
        
        try:
            ftp.mkd('assets')
            print("📁 Created assets directory")
        except:
            pass  # Directory might already exist
        
        ftp.cwd('assets')
        
        assets_path = os.path.join(dist_path, 'assets')
        if os.path.exists(assets_path):
            for filename in os.listdir(assets_path):
                if filename.endswith(('.css', '.js', '.map')):
                    file_path = os.path.join(assets_path, filename)
                    print(f"📦 Uploading assets/{filename}...")
                    with open(file_path, 'rb') as f:
                        ftp.storbinary(f'STOR {filename}', f)
                    print(f"✅ assets/{filename} uploaded successfully")
        else:
            print("❌ Assets folder not found")
            return False
        
        ftp.quit()
        print("\n🚀 FIXED FRONTEND DEPLOYED SUCCESSFULLY!")
        print("📊 Dashboard metrics should now display real-time attack data")
        print("🎯 Dashboard accessible at: https://ftc-compliance.us/dashboard")
        
        return True
        
    except Exception as e:
        print(f"❌ Frontend deployment failed: {e}")
        return False

if __name__ == "__main__":
    deploy_fixed_frontend()
