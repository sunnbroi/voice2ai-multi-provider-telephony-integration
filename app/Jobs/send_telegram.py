import argparse
import requests
import html
from aiogram.utils.markdown import hide_link

def send_telegram_message(token, chat_id, direction, from_number, to_number, status, operator, audio_url, duration, comment, is_conflict):
    arrow = "➟📱" if direction == "in" else "📱➟"
    direction_label = "Входящий" if direction == "in" else "Исходящий"

    operator = html.escape(operator)
    comment = html.escape(comment)

    # Формируем статус с учётом конфликта
    if status == "answered":
        real_stat = '✅Отвечен'
        if is_conflict:
            real_stat = '✅📛Отвечен'
    else:
        real_stat = '❌Не отвечен'
        if is_conflict:
            real_stat += ' 📛Конфликтный'

    audio_block = ""
    if audio_url:
        audio_block = f"Аудиозапись:  — {hide_link(audio_url)}<a href=\"{audio_url}\">{duration}</a>\n"

    text = f'''{arrow}{direction_label} звонок
От: {from_number}
Кому: {to_number}
Статус: {real_stat}
Оператор: {operator}
{audio_block}{comment}'''

    data = {
        'chat_id': chat_id,
        'text': text,
        'parse_mode': 'HTML',
        'disable_web_page_preview': False
    }

    r = requests.post(f'https://api.telegram.org/bot{token}/sendMessage', data=data)
    print(f"Status code: {r.status_code}")
    try:
        print(f"Response: {r.json()}")
    except Exception as e:
        print("Failed to decode response JSON:", e)

if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--token", required=True)
    parser.add_argument("--chat_id", required=True, type=int)
    parser.add_argument("--direction", choices=["in", "out"], required=True)
    parser.add_argument("--from_number", required=True)
    parser.add_argument("--to_number", required=True)
    parser.add_argument("--status", required=True)
    parser.add_argument("--operator", required=True)
    parser.add_argument("--audio_url", default="")
    parser.add_argument("--duration", default="")
    parser.add_argument("--comment", default="")
    parser.add_argument("--is_conflict", default="false")  # <-- добавлен новый аргумент

    args = parser.parse_args()

    # Преобразуем строку в булево значение
    is_conflict = args.is_conflict.lower() == "true"

    send_telegram_message(
        token=args.token,
        chat_id=args.chat_id,
        direction=args.direction,
        from_number=args.from_number,
        to_number=args.to_number,
        status=args.status,
        operator=args.operator,
        audio_url=args.audio_url,
        duration=args.duration,
        comment=args.comment,
        is_conflict=is_conflict  # <-- передаём в функцию
    )
