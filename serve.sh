#!/bin/bash
PORT=8000

case "$1" in
    stop)
        pid=$(lsof -ti tcp:$PORT)
        if [ -n "$pid" ]; then
            kill -9 $pid
            echo "✅ Server on port $PORT stopped."
        else
            echo "⚠️ No server running on port $PORT."
        fi
        ;;
    restart)
        echo "🔄 Restarting server..."
        $0 stop
        sleep 1
        $0 start
        ;;
    start|*)
        pid=$(lsof -ti tcp:$PORT)
        if [ -n "$pid" ]; then
             echo "⚠️ Server is already running on port $PORT (PID: $pid)."
             echo "Use '$0 stop' to stop it, or '$0 restart' to restart."
             exit 1
        fi
        echo "🚀 Starting OpenCollab Server at http://localhost:$PORT"
        echo "⚙️ Configuration: upload_max_filesize=20M, post_max_size=20M"
        exec php -d upload_max_filesize=20M -d post_max_size=20M -S localhost:$PORT -t apps/web
        ;;
esac
